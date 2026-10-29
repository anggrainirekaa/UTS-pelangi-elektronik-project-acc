<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\PriceHistory;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur pembelian:
 *   draft -> pending (diajukan) -> approved (disetujui admin) -> partially_received -> completed
 *   draft/pending/approved -> cancelled (dengan alasan)
 * "Terima langsung" (create) melewati tahap PO: dibuat, disetujui, dan diterima penuh dalam satu transaksi.
 * Stok & jurnal baru berubah pada PENERIMAAN barang, bukan saat PO dibuat.
 */
class PurchaseService
{
    public function __construct(
        protected StockService $stock,
        protected JournalService $journal,
        protected WebhookDispatcher $webhooks,
        protected NotificationService $notifications,
    ) {
    }

    /**
     * Terima langsung: catat pembelian sekaligus menerima seluruh barang (stock-in).
     * Header, item, penambahan stok, dan jurnal disimpan atomik.
     *
     * @param  array{supplier_id: int, warehouse_id?: int|null, note?: string|null, request_key?: string|null, items: array<int, array{barang_id: int, quantity: int, unit_cost: int, batch_number?: string|null, expiry_date?: string|null}>}  $data
     *
     * @throws ValidationException
     */
    public function create(User $user, array $data): Purchase
    {
        return $this->createOrder($user, $data, receiveNow: true);
    }

    /** Susun draft PO (belum ada stok/jurnal). */
    public function createDraft(User $user, array $data): Purchase
    {
        return $this->createOrder($user, $data, receiveNow: false);
    }

    private function createOrder(User $user, array $data, bool $receiveNow): Purchase
    {
        $requestKey = $data['request_key'] ?? null;

        if ($requestKey && ($existing = Purchase::where('request_key', $requestKey)->where('user_id', $user->id)->first())) {
            return $existing;
        }

        $items = collect($data['items'] ?? [])
            ->map(fn ($i) => [
                'barang_id' => (int) $i['barang_id'],
                'quantity' => (int) $i['quantity'],
                'unit_cost' => (int) $i['unit_cost'],
                'batch_number' => $i['batch_number'] ?? null,
                'expiry_date' => $i['expiry_date'] ?? null,
            ])
            ->sortBy('barang_id') // urutan konsisten -> menghindari deadlock
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu barang.']);
        }

        foreach ($items as $item) {
            if ($item['quantity'] < 1 || $item['unit_cost'] < 0) {
                throw ValidationException::withMessages(['items' => 'Kuantitas harus lebih dari 0 dan harga tidak boleh negatif.']);
            }
        }

        try {
            $purchase = DB::transaction(function () use ($user, $data, $items, $receiveNow, $requestKey) {
                $warehouseId = $data['warehouse_id'] ?? Warehouse::default()->id;

                $purchase = Purchase::create([
                    'purchase_number' => NumberGenerator::temporary(),
                    'supplier_id' => $data['supplier_id'],
                    'user_id' => $user->id,
                    'warehouse_id' => $warehouseId,
                    'total' => 0,
                    'status' => $receiveNow ? Purchase::STATUS_APPROVED : Purchase::STATUS_DRAFT,
                    'purchased_at' => now(),
                    'note' => $data['note'] ?? null,
                    'request_key' => $requestKey,
                    'approved_at' => $receiveNow ? now() : null,
                    'approved_by' => $receiveNow ? $user->id : null,
                ]);

                $total = 0;
                $lines = [];

                foreach ($items as $item) {
                    $row = $purchase->items()->create([
                        'barang_id' => $item['barang_id'],
                        'quantity' => $item['quantity'],
                        'received_quantity' => 0,
                        'unit_cost' => $item['unit_cost'],
                        'subtotal' => $item['quantity'] * $item['unit_cost'],
                    ]);

                    $lines[] = ['purchase_item_id' => $row->id, 'quantity' => $item['quantity'], 'batch_number' => $item['batch_number'], 'expiry_date' => $item['expiry_date']];
                    $total += $row->subtotal;
                }

                $purchase->update(['total' => $total]);
                NumberGenerator::finalize($purchase, 'PB', 'purchase_number');

                if ($receiveNow) {
                    $this->receiveInTransaction($purchase, $user, $lines, null, null);
                }

                return $purchase;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($requestKey && ($existing = Purchase::where('request_key', $requestKey)->where('user_id', $user->id)->first())) {
                return $existing;
            }

            throw $e;
        }

        AuditLogger::log('purchase.created', "Pembelian {$purchase->purchase_number} dibuat (".($receiveNow ? 'diterima langsung' : 'draft PO').')', $purchase, null, ['total' => $purchase->total, 'status' => $purchase->status]);

        if ($receiveNow) {
            $this->afterReceived($purchase->fresh(), $user);
        }

        return $purchase->fresh();
    }

    /** Ajukan draft PO untuk persetujuan admin. */
    public function submit(Purchase $purchase, User $actor): Purchase
    {
        $purchase = DB::transaction(function () use ($purchase) {
            $locked = Purchase::lockForUpdate()->findOrFail($purchase->id);

            if ($locked->status !== Purchase::STATUS_DRAFT) {
                throw new BusinessRuleException('Hanya draft PO yang dapat diajukan.');
            }

            $locked->update(['status' => Purchase::STATUS_PENDING, 'submitted_at' => now()]);

            return $locked;
        });

        AuditLogger::log('purchase.submitted', "PO {$purchase->purchase_number} diajukan untuk persetujuan", $purchase);
        $this->notifications->toRoles(['owner'], 'purchase_pending', 'Pembelian menunggu persetujuan', "{$purchase->purchase_number} senilai ".money($purchase->total, base: true).' perlu disetujui.', route('pembelian.show', $purchase), "po-pending:{$purchase->id}", 'warning');

        return $purchase;
    }

    /** Admin menyetujui PO (dari draft maupun yang diajukan). */
    public function approve(Purchase $purchase, User $admin): Purchase
    {
        $purchase = DB::transaction(function () use ($purchase, $admin) {
            $locked = Purchase::lockForUpdate()->findOrFail($purchase->id);

            if (! in_array($locked->status, [Purchase::STATUS_DRAFT, Purchase::STATUS_PENDING], true)) {
                throw new BusinessRuleException('PO ini tidak dalam status yang dapat disetujui.');
            }

            $locked->update(['status' => Purchase::STATUS_APPROVED, 'approved_at' => now(), 'approved_by' => $admin->id]);

            return $locked;
        });

        AuditLogger::log('purchase.approved', "PO {$purchase->purchase_number} disetujui", $purchase, ['status' => 'pending'], ['status' => 'approved']);
        $this->notifications->resolve("po-pending:{$purchase->id}");

        if ($purchase->user_id !== $admin->id) {
            $this->notifications->toUser($purchase->user, 'purchase_approved', 'PO disetujui', "{$purchase->purchase_number} disetujui dan siap diterima.", route('pembelian.show', $purchase), "po-approved:{$purchase->id}", 'success');
        }

        return $purchase;
    }

    /** Admin menolak PO yang diajukan (menjadi dibatalkan dengan alasan). */
    public function reject(Purchase $purchase, User $admin, string $reason): Purchase
    {
        $purchase = $this->cancel($purchase, $admin, 'Ditolak: '.$reason);

        $this->notifications->toUser($purchase->user, 'purchase_rejected', 'PO ditolak', "{$purchase->purchase_number} ditolak: {$reason}", route('pembelian.show', $purchase), "po-rejected:{$purchase->id}", 'danger');

        return $purchase;
    }

    /**
     * Terima barang dari PO yang disetujui, penuh atau sebagian.
     *
     * @param  array<int, array{purchase_item_id: int, quantity: int, batch_number?: string|null, expiry_date?: string|null}>  $lines
     *
     * @throws BusinessRuleException|ValidationException
     */
    public function receive(Purchase $purchase, User $actor, array $lines, ?string $note = null): Purchase
    {
        $purchase = DB::transaction(function () use ($purchase, $actor, $lines, $note) {
            $locked = Purchase::lockForUpdate()->findOrFail($purchase->id);

            if (! $locked->canReceive()) {
                throw new BusinessRuleException('Barang hanya dapat diterima dari PO yang sudah disetujui dan belum selesai.');
            }

            $this->receiveInTransaction($locked, $actor, $lines, $note, null);

            return $locked;
        });

        $this->afterReceived($purchase->fresh(), $actor);

        return $purchase->fresh();
    }

    /**
     * Inti penerimaan (dipanggil dalam transaksi pemanggil): receipt, stok (+batch), harga beli, jurnal, status.
     */
    private function receiveInTransaction(Purchase $purchase, User $actor, array $lines, ?string $note, $receivedAt): PurchaseReceipt
    {
        $items = $purchase->items()->lockForUpdate()->get()->keyBy('id');

        $lines = collect($lines)
            ->map(fn ($l) => [
                'purchase_item_id' => (int) ($l['purchase_item_id'] ?? 0),
                'quantity' => (int) ($l['quantity'] ?? 0),
                'batch_number' => $l['batch_number'] ?? null,
                'expiry_date' => $l['expiry_date'] ?? null,
            ])
            ->filter(fn ($l) => $l['quantity'] > 0)
            ->sortBy(fn ($l) => $items->get($l['purchase_item_id'])?->barang_id)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Isi jumlah yang diterima minimal untuk satu barang.']);
        }

        $receipt = PurchaseReceipt::create([
            'receipt_number' => NumberGenerator::temporary(),
            'purchase_id' => $purchase->id,
            'user_id' => $actor->id,
            'received_at' => $receivedAt ?? now(),
            'total' => 0,
            'note' => $note,
        ]);

        $total = 0;

        foreach ($lines as $line) {
            $item = $items->get($line['purchase_item_id']);

            if (! $item) {
                throw ValidationException::withMessages(['items' => 'Baris penerimaan tidak sesuai dengan PO ini.']);
            }

            if ($line['quantity'] > $item->remaining()) {
                $name = $item->barang?->name ?? 'barang';
                throw new BusinessRuleException("Jumlah diterima untuk {$name} melebihi sisa pesanan ({$item->remaining()}).");
            }

            $barang = Barang::withTrashed()->findOrFail($item->barang_id);
            $expiry = null;

            if ($barang->tracks_expiry) {
                if (blank($line['expiry_date'])) {
                    throw ValidationException::withMessages(['items' => "Tanggal kedaluwarsa wajib diisi untuk {$barang->name}."]);
                }

                $expiry = \Illuminate\Support\Carbon::parse($line['expiry_date'])->startOfDay();

                if ($expiry->lt(today())) {
                    throw ValidationException::withMessages(['items' => "{$barang->name} sudah kedaluwarsa dan tidak dapat diterima."]);
                }
            }

            $receipt->items()->create([
                'purchase_item_id' => $item->id,
                'barang_id' => $item->barang_id,
                'quantity' => $line['quantity'],
                'unit_cost' => $item->unit_cost,
                'batch_number' => $barang->tracks_expiry ? $line['batch_number'] : null,
                'expiry_date' => $expiry?->toDateString(),
            ]);

            $this->stock->increase($item->barang_id, $purchase->warehouse_id, $line['quantity'], [
                'type' => 'purchase', 'reference' => $purchase, 'user_id' => $actor->id, 'note' => $purchase->purchase_number,
                'batch_number' => $line['batch_number'], 'expiry_date' => $expiry?->toDateString(),
                'occurred_at' => $receipt->received_at,
            ]);

            $item->increment('received_quantity', $line['quantity']);

            // Harga pokok terakhir menjadi acuan HPP penjualan berikutnya; perubahan dicatat di riwayat harga.
            if ($barang->purchase_price !== $item->unit_cost) {
                PriceHistory::create([
                    'barang_id' => $barang->id, 'user_id' => $actor->id, 'source' => 'pembelian',
                    'old_purchase_price' => $barang->purchase_price, 'new_purchase_price' => $item->unit_cost,
                    'old_selling_price' => $barang->selling_price, 'new_selling_price' => $barang->selling_price,
                ]);
                Barang::whereKey($barang->id)->update(['purchase_price' => $item->unit_cost]);
            }

            $total += $line['quantity'] * $item->unit_cost;
        }

        $receipt->update(['total' => $total]);
        NumberGenerator::finalize($receipt, 'RC', 'receipt_number');
        $this->journal->postPurchaseReceipt($purchase, $receipt->fresh());

        $complete = $purchase->items()->get()->every(fn ($i) => $i->received_quantity >= $i->quantity);
        $purchase->update(['status' => $complete ? Purchase::STATUS_COMPLETED : Purchase::STATUS_PARTIAL]);

        return $receipt;
    }

    private function afterReceived(Purchase $purchase, User $actor): void
    {
        AuditLogger::log('purchase.received', "Penerimaan barang {$purchase->purchase_number} ({$purchase->statusLabel()})", $purchase, null, ['status' => $purchase->status]);
        $this->notifications->toUser($actor, 'purchase_received', 'Barang diterima', "{$purchase->purchase_number}: ".$purchase->statusLabel().'.', route('pembelian.show', $purchase), "po-received:{$purchase->id}:{$purchase->status}", 'success');

        if ($purchase->isCompleted()) {
            $this->webhooks->dispatch('purchase.completed', $this->payload($purchase));
        }
    }

    /**
     * Batalkan pembelian dengan alasan.
     *  - Belum ada barang diterima (draft/diajukan/disetujui): cukup ubah status.
     *  - Sudah ada penerimaan: stok dikurangi kembali dari batch yang dulu diterima. Ditolak bila stok
     *    sudah terpakai (agar tidak negatif). Jurnal dibalik.
     *
     * @throws BusinessRuleException
     */
    public function cancel(Purchase $purchase, User $actor, ?string $reason = null): Purchase
    {
        $purchase = DB::transaction(function () use ($purchase, $actor, $reason) {
            $locked = Purchase::with('items')->lockForUpdate()->findOrFail($purchase->id);

            if ($locked->isCancelled()) {
                throw new BusinessRuleException('Pembelian ini sudah dibatalkan.');
            }

            $received = $locked->items->groupBy('barang_id')->map(fn ($rows) => (int) $rows->sum('received_quantity'))->filter(fn ($q) => $q > 0);

            foreach ($received->sortKeys() as $barangId => $quantity) {
                $allocations = $this->stock->outstandingAllocations($locked, $barangId, 'in');
                $exact = array_sum(array_column($allocations, 'quantity')) === $quantity;

                try {
                    $this->stock->decrease($barangId, $locked->warehouse_id, $quantity, [
                        'type' => 'purchase_cancel', 'reference' => $locked, 'user_id' => $actor->id, 'note' => 'Pembatalan '.$locked->purchase_number,
                        'allocations' => $exact ? $allocations : [], 'allow_expired' => true,
                    ]);
                } catch (InsufficientStockException $e) {
                    throw new BusinessRuleException(
                        'Pembelian tidak dapat dibatalkan karena stok barang sudah terpakai. '.$e->getMessage()
                    );
                }
            }

            $locked->update([
                'status' => Purchase::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);

            if ($received->isNotEmpty()) {
                $this->journal->reverse($locked, $actor->id);
            }

            return $locked;
        });

        AuditLogger::log('purchase.cancelled', "Pembelian {$purchase->purchase_number} dibatalkan: ".($reason ?: '-'), $purchase, null, ['status' => 'cancelled', 'reason' => $reason]);
        $this->notifications->resolve("po-pending:{$purchase->id}");
        $this->webhooks->dispatch('purchase.cancelled', $this->payload($purchase));

        return $purchase;
    }

    private function payload(Purchase $purchase): array
    {
        $purchase->loadMissing(['items.barang', 'supplier']);

        return [
            'purchase_number' => $purchase->purchase_number,
            'status' => $purchase->status,
            'supplier' => $purchase->supplier?->name,
            'total' => $purchase->total,
            'items' => $purchase->items->map(fn ($i) => [
                'sku' => $i->barang?->code,
                'quantity' => $i->quantity,
                'received_quantity' => $i->received_quantity,
                'unit_cost' => $i->unit_cost,
            ])->all(),
        ];
    }
}
