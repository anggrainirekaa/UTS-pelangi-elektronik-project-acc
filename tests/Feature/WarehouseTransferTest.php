<?php

namespace Tests\Feature;

use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class WarehouseTransferTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function secondWarehouse(): Warehouse
    {
        return Warehouse::create(['code' => 'GDG-002', 'name' => 'Gudang Cabang', 'is_default' => false]);
    }

    public function test_transfer_moves_stock_between_warehouses_without_changing_total(): void
    {
        $a = $this->product(10);
        $main = Warehouse::default();
        $branch = $this->secondWarehouse();

        $this->actingAs($this->staff())->post('/transfer-stok', [
            'from_warehouse_id' => $main->id, 'to_warehouse_id' => $branch->id, 'barang_id' => $a->id, 'quantity' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertSame(6, $this->warehouseStock($a, $main));
        $this->assertSame(4, $this->warehouseStock($a, $branch));
        $this->assertSame(10, $a->fresh()->stock);
        $this->assertStringStartsWith('TF-', StockTransfer::first()->transfer_number);
    }

    public function test_transfer_cannot_exceed_source_stock_and_is_atomic(): void
    {
        $a = $this->product(3);
        $main = Warehouse::default();
        $branch = $this->secondWarehouse();

        $this->actingAs($this->staff())->post('/transfer-stok', [
            'from_warehouse_id' => $main->id, 'to_warehouse_id' => $branch->id, 'barang_id' => $a->id, 'quantity' => 5,
        ])->assertSessionHasErrors();

        $this->assertSame(3, $this->warehouseStock($a, $main));
        $this->assertSame(0, $this->warehouseStock($a, $branch));
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_source_and_destination_must_differ(): void
    {
        $a = $this->product(3);
        $main = Warehouse::default();

        $this->actingAs($this->staff())->post('/transfer-stok', [
            'from_warehouse_id' => $main->id, 'to_warehouse_id' => $main->id, 'barang_id' => $a->id, 'quantity' => 1,
        ])->assertSessionHasErrors('from_warehouse_id');
    }

    public function test_customer_cannot_access_transfers(): void
    {
        $this->actingAs($this->customer())->get('/transfer-stok')->assertForbidden();
    }

    public function test_sale_uses_stock_of_selected_warehouse_only(): void
    {
        $a = $this->product(5);
        $branch = $this->secondWarehouse();

        $this->actingAs($this->staff())->post('/penjualan', [
            'warehouse_id' => $branch->id, 'items' => [['barang_id' => $a->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items'); // gudang cabang kosong

        $this->assertSame(5, $a->fresh()->stock);
    }

    public function test_admin_manages_warehouses_and_staff_cannot(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/gudang')->assertOk();
        $this->actingAs($this->staff())->get('/gudang')->assertForbidden();
        $this->actingAs($this->customer())->get('/gudang')->assertForbidden();
    }
}
