<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Satu-satunya tempat stok diubah. Semua transaksi (pembelian, penjualan, pembatalan, retur,
 * penyesuaian, transfer, opname) memanggil service ini di dalam DB::transaction milik pemanggil.
 *
 * - barangs.stock             = total stok seluruh gudang (dipakai katalog & dashboard)
 * - warehouse_stocks.quantity = stok per gudang
 * - stock_batches.quantity    = rincian batch/kedaluwarsa (hanya produk ber-expiry); selalu <= stok gudang
 * - stock_movements           = kartu stok: setiap perubahan saldo dicatat (audit trail stok)
 *
 * Urutan penguncian selalu: baris barang -> stok gudang -> batch, untuk menghindari deadlock.
 *
 * $meta (opsional) untuk pencatatan & perilaku:
 *   type, reference (Model), user_id, note, occurred_at
 *   increase: expiry_date, batch_number, lots[], allocations[]
 *   decrease: allow_expired (bool), allocations[] (ambil dari batch tertentu)
 */
class StockService
{
    public function __construct(protected StockAlertService $alerts)
    {
    }

    public function increase(int $barangId, int $warehouseId, int $quantity, array $meta = []): void
    {
        $this->assertPositive($quantity);

        $barang = Barang::withTrashed()->lockForUpdate()->findOrFail($barangId);
        $stock = $this->lockedStock($barangId, $warehouseId);

        $balance = $stock->quantity;
        $total = $barang->stock;

        $stock->increment('quantity', $quantity);
        $barang->increment('stock', $quantity);

        foreach ($this->resolveIncomingLots($barang, $warehouseId, $quantity, $meta) as [$batchId, $lotQuantity]) {
            $balance += $lotQuantity;
            $total += $lotQuantity;
            $this->record($barangId, $warehouseId, $batchId, $lotQuantity, $balance, $total, $meta);
        }

        $this->alerts->afterChange($barangId);
    }

    /**
     * Kurangi stok. Produk ber-batch diambil dengan metode FEFO (kedaluwarsa terdekat lebih dulu);
     * batch yang sudah kedaluwarsa tidak ikut dijual kecuali allow_expired = true.
     *
     * @return array<int, array{batch_id: int|null, quantity: int}> alokasi per batch (untuk pembatalan/transfer)
     *
     * @throws InsufficientStockException bila stok yang boleh dijual kurang. Stok tidak pernah menjadi negatif.
     */
    public function decrease(int $barangId, int $warehouseId, int $quantity, array $meta = []): array
    {
        $this->assertPositive($quantity);

        $barang = Barang::withTrashed()->lockForUpdate()->findOrFail($barangId);
        $stock = $this->lockedStock($barangId, $warehouseId);
        $allowExpired = (bool) ($meta['allow_expired'] ?? false);

        $batches = StockBatch::where('barang_id', $barangId)->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)->lockForUpdate()->fefo()->get();

        $expiredQuantity = $allowExpired ? 0 : (int) $batches->filter->isExpired()->sum('quantity');
        $sellable = $stock->quantity - $expiredQuantity;

        if (! empty($meta['allocations'])) {
            $allocations = $this->takeFromSpecificBatches($barang, $stock, $batches, $quantity, $meta['allocations']);
        } else {
            if ($sellable < $quantity) {
                throw new InsufficientStockException($barang->name, max(0, $sellable), $quantity);
            }

            $allocations = [];
            $remaining = $quantity;

            foreach ($allowExpired ? $batches : $batches->reject->isExpired() as $batch) {
                if ($remaining === 0) {
                    break;
                }
                $take = min($batch->quantity, $remaining);
                $allocations[] = ['batch_id' => $batch->id, 'quantity' => $take];
                $remaining -= $take;
            }

            if ($remaining > 0) {
                $allocations[] = ['batch_id' => null, 'quantity' => $remaining]; // stok tanpa batch
            }
        }

        $balance = $stock->quantity;
        $total = $barang->stock;

        $stock->decrement('quantity', $quantity);
        $barang->decrement('stock', $quantity);

        foreach ($allocations as $allocation) {
            if ($allocation['batch_id']) {
                StockBatch::whereKey($allocation['batch_id'])->decrement('quantity', $allocation['quantity']);
            }

            $balance -= $allocation['quantity'];
            $total -= $allocation['quantity'];
            $this->record($barangId, $warehouseId, $allocation['batch_id'], -$allocation['quantity'], $balance, $total, $meta);
        }

        $this->alerts->afterChange($barangId);

        return $allocations;
    }

    /** Stok total gudang (termasuk batch kedaluwarsa). */
    public function available(int $barangId, int $warehouseId): int
    {
        return (int) WarehouseStock::where('barang_id', $barangId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');
    }

    /** Stok yang boleh dijual: stok gudang dikurangi batch yang sudah kedaluwarsa. */
    public function sellable(int $barangId, int $warehouseId): int
    {
        $expired = (int) StockBatch::where('barang_id', $barangId)->where('warehouse_id', $warehouseId)
            ->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today())->sum('quantity');

        return max(0, $this->available($barangId, $warehouseId) - $expired);
    }

    /**
     * Sisa alokasi per batch dari sebuah transaksi (selisih seluruh pergerakan yang merujuk transaksi itu).
     * direction "out": barang yang keluar & belum dikembalikan (untuk pembatalan/retur penjualan).
     * direction "in": barang yang masuk & belum dikeluarkan lagi (untuk pembatalan pembelian).
     *
     * @return array<int, array{batch_id: int|null, quantity: int}>
     */
    public function outstandingAllocations(Model $reference, int $barangId, string $direction = 'out'): array
    {
        $rows = StockMovement::where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->where('barang_id', $barangId)
            ->selectRaw('batch_id, SUM(quantity) as net')
            ->groupBy('batch_id')
            ->orderByRaw('batch_id IS NULL')->orderBy('batch_id')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $amount = $direction === 'out' ? -(int) $row->net : (int) $row->net;

            if ($amount > 0) {
                $result[] = ['batch_id' => $row->batch_id ? (int) $row->batch_id : null, 'quantity' => $amount];
            }
        }

        return $result;
    }

    /**
     * Ambil sebanyak $quantity dari daftar alokasi (untuk retur sebagian).
     *
     * @param  array<int, array{batch_id: int|null, quantity: int}>  $allocations
     * @return array<int, array{batch_id: int|null, quantity: int}>
     */
    public function slice(array $allocations, int $quantity): array
    {
        $result = [];

        foreach ($allocations as $allocation) {
            if ($quantity <= 0) {
                break;
            }
            $take = min($allocation['quantity'], $quantity);
            $result[] = ['batch_id' => $allocation['batch_id'], 'quantity' => $take];
            $quantity -= $take;
        }

        return $result;
    }

    /**
     * Ubah alokasi batch menjadi "lot" yang bisa dibuat ulang di gudang tujuan (transfer).
     *
     * @param  array<int, array{batch_id: int|null, quantity: int}>  $allocations
     * @return array<int, array{batch_number: string|null, expiry_date: mixed, quantity: int}>
     */
    public function lotsFromAllocations(array $allocations): array
    {
        $lots = [];

        foreach ($allocations as $allocation) {
            if (! $allocation['batch_id']) {
                continue;
            }

            $batch = StockBatch::find($allocation['batch_id']);

            if ($batch) {
                $lots[] = ['batch_number' => $batch->batch_number, 'expiry_date' => $batch->expiry_date, 'quantity' => $allocation['quantity']];
            }
        }

        return $lots;
    }

    /**
     * @return array<int, array{0: int|null, 1: int}> [batch_id|null, quantity]
     */
    private function resolveIncomingLots(Barang $barang, int $warehouseId, int $quantity, array $meta): array
    {
        $lots = [];
        $assigned = 0;

        if (! empty($meta['allocations'])) {
            // Pengembalian ke batch asal (pembatalan penjualan / retur).
            foreach ($meta['allocations'] as $allocation) {
                $take = min($allocation['quantity'], $quantity - $assigned);

                if ($take <= 0) {
                    break;
                }

                if ($allocation['batch_id']) {
                    $batch = StockBatch::lockForUpdate()->find($allocation['batch_id']);

                    if ($batch && $batch->barang_id === $barang->id && $batch->warehouse_id === $warehouseId) {
                        $batch->increment('quantity', $take);
                        $lots[] = [$batch->id, $take];
                        $assigned += $take;

                        continue;
                    }
                }

                $lots[] = [null, $take];
                $assigned += $take;
            }
        } elseif ($barang->tracks_expiry) {
            $incoming = $meta['lots'] ?? (isset($meta['expiry_date']) || isset($meta['batch_number'])
                ? [['batch_number' => $meta['batch_number'] ?? null, 'expiry_date' => $meta['expiry_date'] ?? null, 'quantity' => $quantity]]
                : []);

            foreach ($incoming as $lot) {
                $take = min((int) $lot['quantity'], $quantity - $assigned);

                if ($take <= 0) {
                    continue;
                }

                $batch = $this->findOrCreateBatch($barang->id, $warehouseId, $lot['batch_number'] ?? null, $lot['expiry_date'] ?? null);
                $batch->increment('quantity', $take);
                $lots[] = [$batch->id, $take];
                $assigned += $take;
            }
        }

        if ($assigned < $quantity) {
            $lots[] = [null, $quantity - $assigned];
        }

        return $lots;
    }

    private function findOrCreateBatch(int $barangId, int $warehouseId, ?string $number, mixed $expiry): StockBatch
    {
        $expiry = $expiry ? \Illuminate\Support\Carbon::parse($expiry)->toDateString() : null;
        $number = $number ?: 'LOT-'.($expiry ? str_replace('-', '', $expiry) : now()->format('Ymd'));

        $query = StockBatch::where('barang_id', $barangId)->where('warehouse_id', $warehouseId)->where('batch_number', $number)
            ->when($expiry, fn ($q) => $q->whereDate('expiry_date', $expiry), fn ($q) => $q->whereNull('expiry_date'))
            ->lockForUpdate();

        return $query->first() ?? StockBatch::create([
            'barang_id' => $barangId, 'warehouse_id' => $warehouseId, 'batch_number' => $number,
            'expiry_date' => $expiry, 'quantity' => 0, 'received_at' => now(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StockBatch>  $batches
     * @param  array<int, array{batch_id: int|null, quantity: int}>  $requested
     * @return array<int, array{batch_id: int|null, quantity: int}>
     */
    private function takeFromSpecificBatches(Barang $barang, WarehouseStock $stock, $batches, int $quantity, array $requested): array
    {
        $allocations = [];
        $sum = 0;
        $unbatched = $stock->quantity - (int) $batches->sum('quantity');

        foreach ($requested as $allocation) {
            $take = min($allocation['quantity'], $quantity - $sum);

            if ($take <= 0) {
                break;
            }

            $available = $allocation['batch_id']
                ? (int) optional($batches->firstWhere('id', $allocation['batch_id']))->quantity
                : $unbatched;

            if ($available < $take) {
                throw new InsufficientStockException($barang->name, $available, $take);
            }

            $allocations[] = ['batch_id' => $allocation['batch_id'], 'quantity' => $take];
            $sum += $take;
        }

        if ($sum !== $quantity) {
            throw new InsufficientStockException($barang->name, $sum, $quantity);
        }

        return $allocations;
    }

    private function record(int $barangId, int $warehouseId, ?int $batchId, int $signedQuantity, int $balance, int $total, array $meta): void
    {
        $reference = $meta['reference'] ?? null;

        StockMovement::create([
            'barang_id' => $barangId,
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
            'type' => $meta['type'] ?? 'manual',
            'quantity' => $signedQuantity,
            'balance_after' => $balance,
            'total_after' => $total,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $meta['user_id'] ?? Auth::id(),
            'note' => $meta['note'] ?? null,
            'occurred_at' => $meta['occurred_at'] ?? now(),
        ]);
    }

    private function lockedStock(int $barangId, int $warehouseId): WarehouseStock
    {
        return WarehouseStock::where('barang_id', $barangId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first()
            ?? WarehouseStock::create([
                'barang_id' => $barangId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
            ]);
    }

    private function assertPositive(int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Kuantitas harus lebih dari 0.');
        }
    }
}
