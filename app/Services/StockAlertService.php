<?php

namespace App\Services;

use App\Models\Barang;

/**
 * Smart Low Stock Alert: dipanggil StockService setiap saldo berubah
 * (pembelian, penjualan, retur, transfer, penyesuaian, opname).
 */
class StockAlertService
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function afterChange(int $barangId): void
    {
        $barang = Barang::withTrashed()->find($barangId, ['id', 'name', 'code', 'unit', 'stock', 'minimum_stock', 'is_active', 'deleted_at']);

        if (! $barang || $barang->trashed()) {
            return;
        }

        $url = route('barang.show', $barang);

        if ($barang->stock <= 0) {
            $this->notifications->toRoles(['owner', 'staff'], 'out_of_stock', 'Stok habis', "{$barang->name} ({$barang->code}) kehabisan stok.", $url, "out:{$barang->id}", 'danger');
        } elseif ($barang->stock <= $barang->minimum_stock) {
            $this->notifications->resolve("out:{$barang->id}");
            $this->notifications->toRoles(['owner', 'staff'], 'low_stock', 'Stok menipis', "{$barang->name} tersisa {$barang->stock} {$barang->unit} (minimum {$barang->minimum_stock}).", $url, "low:{$barang->id}", 'warning');
        } else {
            $this->notifications->resolve("out:{$barang->id}", "low:{$barang->id}");
        }
    }
}
