<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Barang;
use App\Models\StockOut;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Penyesuaian stok keluar non-penjualan (pemakaian internal, rusak/hilang).
 */
class StockAdjustmentService
{
    public function __construct(protected StockService $stock, protected JournalService $journal)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \App\Exceptions\InsufficientStockException
     */
    public function store(array $data): StockOut
    {
        return DB::transaction(function () use ($data) {
            $data['warehouse_id'] = $data['warehouse_id'] ?? Warehouse::default()->id;
            $data['selling_price'] = $data['selling_price'] ?? 0;

            $stockOut = StockOut::create($data);

            // Barang kedaluwarsa/rusak boleh dikeluarkan dari batch yang sudah lewat tanggal.
            $this->stock->decrease($data['barang_id'], $data['warehouse_id'], $data['quantity'], [
                'type' => 'adjustment', 'reference' => $stockOut, 'user_id' => $data['user_id'] ?? null,
                'note' => 'Penyesuaian: '.$stockOut->purpose_label, 'allow_expired' => true,
            ]);
            $cost = (int) Barang::withTrashed()->whereKey($data['barang_id'])->value('purchase_price');
            $this->journal->postStockAdjustment($stockOut, $cost);

            AuditLogger::log('stock.adjustment', "Penyesuaian stok: {$stockOut->quantity} x barang #{$stockOut->barang_id} ({$stockOut->purpose_label})", $stockOut, null, ['purpose' => $stockOut->purpose, 'quantity' => $stockOut->quantity]);

            return $stockOut;
        });
    }

    public function destroy(StockOut $stockOut): void
    {
        DB::transaction(function () use ($stockOut) {
            $this->stock->increase(
                $stockOut->barang_id,
                $stockOut->warehouse_id ?? Warehouse::default()->id,
                $stockOut->quantity,
                [
                    'type' => 'manual', 'reference' => $stockOut, 'note' => 'Pembatalan penyesuaian stok',
                    'allocations' => $this->stock->outstandingAllocations($stockOut, $stockOut->barang_id, 'out'),
                ]
            );

            $this->journal->reverse($stockOut);
            $stockOut->delete();
        });
    }
}
