<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function pos($user, array $items, array $extra = [])
    {
        return $this->actingAs($user)->post('/penjualan', $extra + [
            'warehouse_id' => Warehouse::default()->id,
            'items' => $items,
        ]);
    }

    public function test_successful_sale_reduces_stock_and_computes_total_on_server(): void
    {
        $a = $this->product(10, ['selling_price' => 15000]);
        $b = $this->product(10, ['selling_price' => 2000]);

        // Browser mencoba memalsukan harga & total: harus diabaikan.
        $this->pos($this->staff(), [
            ['barang_id' => $a->id, 'quantity' => 3, 'unit_price' => 1, 'subtotal' => 1],
            ['barang_id' => $b->id, 'quantity' => 2, 'unit_price' => 1],
        ], ['total' => 1])->assertRedirect();

        $sale = Sale::first();
        $this->assertSame(3 * 15000 + 2 * 2000, $sale->total);
        $this->assertSame(7, $a->fresh()->stock);
        $this->assertSame(8, $b->fresh()->stock);
        $this->assertSame(7, $this->warehouseStock($a));
        $this->assertSame('completed', $sale->status);
        $this->assertSame('pos', $sale->channel);
        $this->assertSame(15000, $sale->items()->where('barang_id', $a->id)->value('unit_price'));
        $this->assertStringStartsWith('SL-', $sale->sale_number);
    }

    public function test_sale_with_insufficient_stock_is_rejected_without_any_change(): void
    {
        $a = $this->product(4);

        $response = $this->pos($this->staff(), [['barang_id' => $a->id, 'quantity' => 6]]);

        $response->assertSessionHasErrors('items');
        $this->assertStringContainsString('Stok tidak mencukupi', session('errors')->first('items'));
        $this->assertSame(4, $a->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_out_of_stock_product_cannot_be_sold(): void
    {
        $a = $this->product(0);

        $this->pos($this->staff(), [['barang_id' => $a->id, 'quantity' => 1]])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_is_atomic_when_second_item_has_insufficient_stock(): void
    {
        $a = $this->product(10);
        $b = $this->product(1);

        $this->pos($this->staff(), [
            ['barang_id' => $a->id, 'quantity' => 5],
            ['barang_id' => $b->id, 'quantity' => 3],
        ])->assertSessionHasErrors('items');

        // Rollback penuh: stok item pertama tidak berubah, tidak ada header/detail tersimpan.
        $this->assertSame(10, $a->fresh()->stock);
        $this->assertSame(10, $this->warehouseStock($a));
        $this->assertSame(1, $b->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_same_product_split_over_lines_cannot_bypass_stock_check(): void
    {
        $a = $this->product(5);

        $this->pos($this->staff(), [
            ['barang_id' => $a->id, 'quantity' => 3],
            ['barang_id' => $a->id, 'quantity' => 3],
        ])->assertSessionHasErrors('items');

        $this->assertSame(5, $a->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_duplicate_lines_are_merged_when_stock_is_enough(): void
    {
        $a = $this->product(10);

        $this->pos($this->staff(), [
            ['barang_id' => $a->id, 'quantity' => 2],
            ['barang_id' => $a->id, 'quantity' => 3],
        ]);

        $this->assertSame(5, $a->fresh()->stock);
        $this->assertSame(1, Sale::first()->items()->count());
        $this->assertSame(5, Sale::first()->items()->first()->quantity);
    }

    public function test_quantity_must_be_positive_integer(): void
    {
        $a = $this->product(10);

        foreach ([0, -3, 'abc', 1.5] as $bad) {
            $this->pos($this->staff(), [['barang_id' => $a->id, 'quantity' => $bad]])->assertSessionHasErrors('items.0.quantity');
        }

        $this->assertSame(10, $a->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_requires_at_least_one_item(): void
    {
        $this->pos($this->staff(), [])->assertSessionHasErrors('items');
    }

    public function test_service_level_guard_prevents_negative_stock_even_without_validation(): void
    {
        $a = $this->product(3);

        $this->expectException(\App\Exceptions\InsufficientStockException::class);
        app(SaleService::class)->createPos($this->staff(), ['items' => [['barang_id' => $a->id, 'quantity' => 4]]]);
    }

    public function test_service_throws_validation_exception_for_invalid_quantity(): void
    {
        $a = $this->product(3);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SaleService::class)->createPos($this->staff(), ['items' => [['barang_id' => $a->id, 'quantity' => 0]]]);
    }

    public function test_sale_can_be_assigned_to_customer_and_marked_paid(): void
    {
        $a = $this->product(10);
        $customer = $this->customer();

        $this->pos($this->staff(), [['barang_id' => $a->id, 'quantity' => 1]], ['customer_id' => $customer->id, 'mark_paid' => 1, 'payment_method' => 'cash']);

        $sale = Sale::first();
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertTrue($sale->isPaid());
    }

    public function test_admin_can_cancel_completed_sale_and_stock_is_restored(): void
    {
        $a = $this->product(10);
        $this->pos($this->staff(), [['barang_id' => $a->id, 'quantity' => 4]], ['mark_paid' => 1]);
        $sale = Sale::first();
        $this->assertSame(6, $a->fresh()->stock);

        $this->actingAs($this->admin())->post("/penjualan/{$sale->id}/batal", ['reason' => 'Retur'])->assertSessionHas('success');

        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
        $this->assertSame('refunded', $sale->payment_status);
        $this->assertSame(10, $a->fresh()->stock);
        $this->assertSame(10, $this->warehouseStock($a));
    }

    public function test_cancel_twice_does_not_restore_stock_twice(): void
    {
        $a = $this->product(10);
        $this->pos($this->staff(), [['barang_id' => $a->id, 'quantity' => 4]]);
        $sale = Sale::first();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/penjualan/{$sale->id}/batal");
        $this->actingAs($admin)->post("/penjualan/{$sale->id}/batal")->assertForbidden(); // sudah dibatalkan

        $this->assertSame(10, $a->fresh()->stock);
    }

    public function test_staff_cannot_cancel_completed_sale(): void
    {
        $a = $this->product(10);
        $staff = $this->staff();
        $this->pos($staff, [['barang_id' => $a->id, 'quantity' => 4]]);
        $sale = Sale::first();

        $this->actingAs($staff)->post("/penjualan/{$sale->id}/batal")->assertForbidden();

        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertSame(6, $a->fresh()->stock);
    }

    public function test_staff_only_sees_own_pos_sales_plus_online_orders(): void
    {
        $a = $this->product(20);
        $staffA = $this->staff();
        $staffB = $this->staff();

        $this->pos($staffA, [['barang_id' => $a->id, 'quantity' => 1]]);
        $saleA = Sale::first();
        $this->flushSession(); // buang pesan flash yang memuat nomor penjualan

        $this->actingAs($staffB)->get('/penjualan')->assertDontSee($saleA->sale_number);
        $this->actingAs($staffB)->get("/penjualan/{$saleA->id}")->assertForbidden();
        $this->actingAs($staffA)->get('/penjualan')->assertSee($saleA->sale_number);
        $this->actingAs($this->admin())->get("/penjualan/{$saleA->id}")->assertOk();

        // Pesanan online terlihat oleh semua staff (staff yang memprosesnya).
        $customer = $this->customer();
        $order = app(SaleService::class)->createOrder($customer, ['shipping_address' => 'Jl. Contoh 1, Aceh', 'items' => [['barang_id' => $a->id, 'quantity' => 1]]]);
        $this->actingAs($staffB)->get("/penjualan/{$order->id}")->assertOk();
    }

    public function test_sales_list_search_filter_and_pagination(): void
    {
        $a = $this->product(100);
        $staff = $this->staff();
        foreach (range(1, 12) as $i) {
            $this->pos($staff, [['barang_id' => $a->id, 'quantity' => 1]]);
        }
        $last = Sale::orderByDesc('id')->first();
        $this->actingAs($this->admin())->post("/penjualan/{$last->id}/batal");

        $admin = $this->admin();
        $this->actingAs($admin)->get('/penjualan')->assertOk();
        $this->actingAs($admin)->get('/penjualan?page=2')->assertOk();
        $this->actingAs($admin)->get('/penjualan?search='.$last->sale_number)->assertSee($last->sale_number);

        $cancelled = $this->actingAs($admin)->get('/penjualan?status=cancelled');
        $cancelled->assertSee($last->sale_number);
        $this->assertSame(1, substr_count(substr($cancelled->getContent(), strpos($cancelled->getContent(), '<main')), 'SL-'));

        $this->actingAs($admin)->get('/penjualan?status=bogus')->assertSessionHasErrors('status');
        $this->actingAs($admin)->get('/penjualan?start_date=2030-01-01')->assertSee('Belum ada penjualan');
    }

    public function test_sale_with_unknown_customer_or_non_customer_role_is_rejected(): void
    {
        $a = $this->product(5);

        $this->pos($this->staff(), [['barang_id' => $a->id, 'quantity' => 1]], ['customer_id' => $this->admin()->id])->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_dashboard_numbers_come_from_database(): void
    {
        $a = $this->product(20, ['selling_price' => 10000, 'minimum_stock' => 5]);
        $this->product(1, ['minimum_stock' => 5]); // stok rendah
        $staff = $this->staff();

        $this->pos($staff, [['barang_id' => $a->id, 'quantity' => 3]]);
        $this->pos($staff, [['barang_id' => $a->id, 'quantity' => 2]]);

        $response = $this->actingAs($this->admin())->get('/dashboard');
        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertSame(50000, $stats['sales']['value']);
        $this->assertSame(1, $stats['low_stock']['value']);
        $this->assertSame(2, $stats['active_products']['value']);
        $this->assertSame(0, $stats['out_of_stock']['value']);
        // Tanpa data periode sebelumnya: tidak ada persentase palsu.
        $this->assertNull($stats['sales']['pct']);
        $response->assertSee('Belum cukup data');
    }
}
