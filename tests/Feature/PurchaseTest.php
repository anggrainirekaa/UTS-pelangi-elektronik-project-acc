<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function post_purchase($user, array $items, array $extra = [])
    {
        return $this->actingAs($user)->post('/pembelian', $extra + [
            'supplier_id' => $this->supplier()->id,
            'warehouse_id' => Warehouse::default()->id,
            'items' => $items,
        ]);
    }

    public function test_purchase_with_multiple_items_increases_stock_and_records_header_and_items(): void
    {
        $staff = $this->staff();
        $a = $this->product(10);
        $b = $this->product(0);

        $response = $this->post_purchase($staff, [
            ['barang_id' => $a->id, 'quantity' => 5, 'unit_cost' => 9000],
            ['barang_id' => $b->id, 'quantity' => 20, 'unit_cost' => 4000],
        ]);

        $purchase = Purchase::first();
        $response->assertRedirect(route('pembelian.show', $purchase));

        $this->assertSame(15, $a->fresh()->stock);
        $this->assertSame(20, $b->fresh()->stock);
        $this->assertSame(15, $this->warehouseStock($a));
        $this->assertSame(5 * 9000 + 20 * 4000, $purchase->total);
        $this->assertSame($staff->id, $purchase->user_id);
        $this->assertStringStartsWith('PB-', $purchase->purchase_number);
        $this->assertDatabaseCount('purchase_items', 2);
        $this->assertSame('completed', $purchase->status);
    }

    public function test_purchase_updates_latest_cost_of_product(): void
    {
        $a = $this->product(0, ['purchase_price' => 1000, 'selling_price' => 5000]);

        $this->post_purchase($this->staff(), [['barang_id' => $a->id, 'quantity' => 1, 'unit_cost' => 2500]]);

        $this->assertSame(2500, $a->fresh()->purchase_price);
    }

    public function test_purchase_validation_rejects_bad_quantity_price_and_empty_items(): void
    {
        $staff = $this->staff();
        $a = $this->product(0);

        $this->post_purchase($staff, [['barang_id' => $a->id, 'quantity' => 0, 'unit_cost' => 1000]])->assertSessionHasErrors('items.0.quantity');
        $this->post_purchase($staff, [['barang_id' => $a->id, 'quantity' => 1, 'unit_cost' => -5]])->assertSessionHasErrors('items.0.unit_cost');
        $this->post_purchase($staff, [])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('purchases', 0);
        $this->assertSame(0, $a->fresh()->stock);
    }

    public function test_purchase_is_atomic_when_one_item_fails(): void
    {
        $staff = $this->staff();
        $a = $this->product(0);

        // barang_id kedua tidak ada -> validasi gagal, tidak ada yang tersimpan
        $this->post_purchase($staff, [
            ['barang_id' => $a->id, 'quantity' => 5, 'unit_cost' => 1000],
            ['barang_id' => 99999, 'quantity' => 5, 'unit_cost' => 1000],
        ])->assertSessionHasErrors('items.1.barang_id');

        $this->assertSame(0, $a->fresh()->stock);
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_admin_can_cancel_purchase_and_stock_is_restored(): void
    {
        $a = $this->product(0);
        $this->post_purchase($this->staff(), [['barang_id' => $a->id, 'quantity' => 10, 'unit_cost' => 1000]]);
        $purchase = Purchase::first();
        $this->assertSame(10, $a->fresh()->stock);

        $this->actingAs($this->admin())->post("/pembelian/{$purchase->id}/batal", ['reason' => 'Salah input'])->assertSessionHas('success');

        $purchase->refresh();
        $this->assertSame('cancelled', $purchase->status);
        $this->assertSame('Salah input', $purchase->cancel_reason);
        $this->assertSame(0, $a->fresh()->stock);
        $this->assertSame(0, $this->warehouseStock($a));
    }

    public function test_purchase_cancel_is_rejected_when_stock_already_used(): void
    {
        $a = $this->product(0);
        $this->post_purchase($this->staff(), [['barang_id' => $a->id, 'quantity' => 10, 'unit_cost' => 1000]]);
        $purchase = Purchase::first();

        $this->actingAs($this->staff())->post('/penjualan', [
            'warehouse_id' => Warehouse::default()->id, 'items' => [['barang_id' => $a->id, 'quantity' => 8]],
        ]);
        $this->assertSame(2, $a->fresh()->stock);

        $this->actingAs($this->admin())->post("/pembelian/{$purchase->id}/batal", ['reason' => 'Uji pembatalan'])->assertSessionHas('error');

        $this->assertSame('completed', $purchase->fresh()->status);
        $this->assertSame(2, $a->fresh()->stock);
    }

    public function test_purchase_cannot_be_cancelled_twice(): void
    {
        $a = $this->product(0);
        $this->post_purchase($this->staff(), [['barang_id' => $a->id, 'quantity' => 4, 'unit_cost' => 1000]]);
        $purchase = Purchase::first();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/pembelian/{$purchase->id}/batal", ['reason' => 'Uji pembatalan']);
        $this->actingAs($admin)->post("/pembelian/{$purchase->id}/batal", ['reason' => 'Uji pembatalan'])->assertSessionHas('error');

        $this->assertSame(0, $a->fresh()->stock); // tidak berkurang dua kali (tidak negatif)
    }

    public function test_staff_cannot_cancel_purchase_and_only_sees_own_purchases(): void
    {
        $a = $this->product(0);
        $staffA = $this->staff();
        $staffB = $this->staff();

        $this->post_purchase($staffA, [['barang_id' => $a->id, 'quantity' => 4, 'unit_cost' => 1000]]);
        $purchase = Purchase::first();

        $this->actingAs($staffA)->post("/pembelian/{$purchase->id}/batal", ['reason' => 'Uji pembatalan'])->assertForbidden();

        $this->actingAs($staffB)->get("/pembelian/{$purchase->id}")->assertForbidden();
        $this->actingAs($staffB)->get('/pembelian')->assertDontSee($purchase->purchase_number);
        $this->actingAs($staffA)->get('/pembelian')->assertSee($purchase->purchase_number);
        $this->actingAs($this->admin())->get('/pembelian')->assertSee($purchase->purchase_number);
    }

    public function test_purchase_list_filters_by_status_and_date(): void
    {
        $a = $this->product(0);
        $staff = $this->staff();
        $this->post_purchase($staff, [['barang_id' => $a->id, 'quantity' => 1, 'unit_cost' => 1000]]);
        $this->post_purchase($staff, [['barang_id' => $a->id, 'quantity' => 1, 'unit_cost' => 1000]]);
        $second = Purchase::orderByDesc('id')->first();
        $this->actingAs($this->admin())->post("/pembelian/{$second->id}/batal", ['reason' => 'Uji filter']);

        $cancelled = $this->actingAs($this->admin())->get('/pembelian?status=cancelled');
        $main = substr($cancelled->getContent(), strpos($cancelled->getContent(), '<main'));
        $this->assertStringContainsString($second->purchase_number, $main);
        $this->assertStringNotContainsString(Purchase::orderBy('id')->first()->purchase_number, $main);

        $this->actingAs($this->admin())->get('/pembelian?start_date='.now()->addDay()->toDateString())->assertSee('Belum ada pembelian');
    }
}
