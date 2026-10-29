<?php

namespace Tests\Concerns;

use App\Models\Barang;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

trait InteractsWithStock
{
    /** Buat barang dengan stok awal yang konsisten (stok barang = stok gudang). */
    protected function product(int $stock = 0, array $attributes = [], ?Warehouse $warehouse = null): Barang
    {
        $barang = Barang::factory()->create($attributes + [
            'stock' => 0,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'minimum_stock' => 5,
            'is_active' => true,
        ]);

        if ($stock > 0) {
            $this->addStock($barang, $stock, $warehouse);
        }

        return $barang->fresh();
    }

    protected function addStock(Barang $barang, int $quantity, ?Warehouse $warehouse = null): void
    {
        $warehouse ??= Warehouse::default();

        DB::transaction(fn () => app(StockService::class)->increase($barang->id, $warehouse->id, $quantity));
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function staff(): User
    {
        return User::factory()->staff()->create();
    }

    protected function customer(): User
    {
        return User::factory()->pelanggan()->create();
    }

    protected function supplier(): Supplier
    {
        return Supplier::factory()->create();
    }

    protected function warehouseStock(Barang $barang, ?Warehouse $warehouse = null): int
    {
        return app(StockService::class)->available($barang->id, ($warehouse ?? Warehouse::default())->id);
    }
}
