<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Alur pelanggan: katalog -> keranjang -> checkout -> bayar -> diproses staff.
 */
class OrderFlowTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function checkout($customer, $barang, int $qty = 2)
    {
        $this->actingAs($customer)->post("/keranjang/{$barang->id}", ['quantity' => $qty]);

        return $this->actingAs($customer)->post('/keranjang/checkout', ['shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe']);
    }

    public function test_storefront_uses_marketplace_layout_with_real_sold_counts_and_sorting(): void
    {
        $slow = $this->product(50, ['name' => 'Barang Sepi']);
        $hot = $this->product(50, ['name' => 'Barang Laris']);
        $customer = $this->customer();

        $order = app(\App\Services\SaleService::class)->createPos($this->staff(), ['warehouse_id' => Warehouse::default()->id, 'items' => [['barang_id' => $hot->id, 'quantity' => 4]], 'mark_paid' => true, 'payment_method' => 'cash']);
        $this->assertTrue($order->isCompleted());

        $r = $this->actingAs($customer)->get('/katalog?sort=popular')->assertOk();
        $r->assertSee('class="sh-header', false)->assertDontSee('sf-sidebar', false)->assertSee('Terjual 4');
        $r->assertSeeInOrder(['Barang Laris', 'Barang Sepi']);
        $r->assertDontSee('Terjual 0');

        $this->actingAs($customer)->get('/katalog?sort=bogus')->assertSessionHasErrors('sort');
        $this->actingAs($customer)->get('/dashboard')->assertOk()->assertSee('sh-header', false);
        $this->actingAs($customer)->get("/katalog/{$hot->id}")->assertOk()->assertSee('Terjual')->assertSee('Produk serupa', false) ;
    }

    public function test_catalog_shows_only_active_products_and_supports_search(): void
    {
        $this->product(5, ['name' => 'Produk Tampil']);
        $this->product(5, ['name' => 'Produk Disembunyikan', 'is_active' => false]);

        $this->actingAs($this->customer())->get('/katalog')->assertSee('Produk Tampil')->assertDontSee('Produk Disembunyikan');
        $this->actingAs($this->customer())->get('/katalog?search=Tampil')->assertSee('Produk Tampil');
    }

    public function test_hidden_product_cannot_be_viewed_or_added_to_cart(): void
    {
        $hidden = $this->product(5, ['is_active' => false]);
        $customer = $this->customer();

        $this->actingAs($customer)->get("/katalog/{$hidden->id}")->assertNotFound();
        $this->actingAs($customer)->post("/keranjang/{$hidden->id}")->assertNotFound();
    }

    public function test_cart_rejects_quantity_above_stock(): void
    {
        $barang = $this->product(3);

        $this->actingAs($this->customer())->post("/keranjang/{$barang->id}", ['quantity' => 4])->assertSessionHas('error');
        $this->assertEmpty(session('cart', []));
    }

    public function test_checkout_creates_pending_order_without_reducing_stock(): void
    {
        $barang = $this->product(10, ['selling_price' => 20000]);
        $customer = $this->customer();

        $response = $this->checkout($customer, $barang, 3);

        $sale = Sale::first();
        $response->assertRedirect(route('pesanan.show', $sale));
        $this->assertSame('pending', $sale->status);
        $this->assertSame('online', $sale->channel);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame(60000, $sale->total);
        $this->assertSame(10, $barang->fresh()->stock); // stok baru berkurang saat diproses staff
        $this->assertEmpty(session('cart', []));
    }

    public function test_checkout_requires_address_and_non_empty_cart(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->post('/keranjang/checkout', ['shipping_address' => 'x'])->assertSessionHasErrors('shipping_address');

        $barang = $this->product(5);
        $this->actingAs($customer)->post("/keranjang/{$barang->id}");
        $this->actingAs($customer)->post('/keranjang/checkout', [])->assertSessionHasErrors('shipping_address');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_checkout_is_rejected_when_stock_dropped_after_adding_to_cart(): void
    {
        $barang = $this->product(5);
        $customer = $this->customer();
        $this->actingAs($customer)->post("/keranjang/{$barang->id}", ['quantity' => 5]);

        // Stok dikurangi pihak lain setelah barang masuk keranjang.
        $this->actingAs($this->staff())->post('/penjualan', ['warehouse_id' => Warehouse::default()->id, 'items' => [['barang_id' => $barang->id, 'quantity' => 4]]]);

        $this->actingAs($customer)->post('/keranjang/checkout', ['shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe'])->assertSessionHas('error');
        $this->assertSame(0, Sale::where('channel', 'online')->count());
    }

    public function test_staff_processing_order_reduces_stock(): void
    {
        $barang = $this->product(10);
        $this->checkout($this->customer(), $barang, 4);
        $sale = Sale::first();

        $this->actingAs($this->staff())->post("/penjualan/{$sale->id}/proses", ['warehouse_id' => Warehouse::default()->id])->assertSessionHas('success');

        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertSame(6, $barang->fresh()->stock);
        $this->assertSame(6, $this->warehouseStock($barang));
    }

    public function test_processing_fails_and_order_stays_pending_if_stock_is_gone(): void
    {
        $barang = $this->product(5);
        $this->checkout($this->customer(), $barang, 5);
        $sale = Sale::first();

        $this->actingAs($this->staff())->post('/penjualan', ['warehouse_id' => Warehouse::default()->id, 'items' => [['barang_id' => $barang->id, 'quantity' => 3]]]);

        $this->actingAs($this->staff())->post("/penjualan/{$sale->id}/proses")->assertSessionHas('error');

        $this->assertSame('pending', $sale->fresh()->status);
        $this->assertSame(2, $barang->fresh()->stock);
    }

    public function test_only_pending_orders_can_be_processed_and_only_once(): void
    {
        $barang = $this->product(10);
        $this->checkout($this->customer(), $barang, 2);
        $sale = Sale::first();
        $staff = $this->staff();

        $this->actingAs($staff)->post("/penjualan/{$sale->id}/proses");
        $this->actingAs($staff)->post("/penjualan/{$sale->id}/proses")->assertSessionHas('error');

        $this->assertSame(8, $barang->fresh()->stock); // hanya berkurang sekali
    }

    public function test_customer_can_cancel_own_pending_order_without_stock_change(): void
    {
        $barang = $this->product(10);
        $customer = $this->customer();
        $this->checkout($customer, $barang, 2);
        $sale = Sale::first();

        $this->actingAs($customer)->post("/pesanan-saya/{$sale->id}/batal")->assertSessionHas('success');

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame(10, $barang->fresh()->stock);
    }

    public function test_customer_cannot_cancel_completed_order(): void
    {
        $barang = $this->product(10);
        $customer = $this->customer();
        $this->checkout($customer, $barang, 2);
        $sale = Sale::first();
        $this->actingAs($this->staff())->post("/penjualan/{$sale->id}/proses");

        $this->actingAs($customer)->post("/pesanan-saya/{$sale->id}/batal")->assertForbidden();
        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_customer_cannot_see_or_touch_other_customers_orders(): void
    {
        $barang = $this->product(10);
        $owner = $this->customer();
        $intruder = $this->customer();
        $this->checkout($owner, $barang, 2);
        $sale = Sale::first();

        $this->actingAs($intruder)->get("/pesanan-saya/{$sale->id}")->assertForbidden();
        $this->actingAs($intruder)->post("/pesanan-saya/{$sale->id}/batal")->assertForbidden();
        $this->actingAs($intruder)->post("/pesanan-saya/{$sale->id}/bayar", ['payment_method' => 'qris'])->assertForbidden();
        $this->actingAs($intruder)->get("/penjualan/{$sale->id}/invoice")->assertForbidden();
        $this->actingAs($intruder)->get('/pesanan-saya')->assertDontSee($sale->sale_number);
        $this->assertSame('pending', $sale->fresh()->status);
    }

    public function test_customer_cannot_use_staff_endpoints_for_own_order(): void
    {
        $barang = $this->product(10);
        $customer = $this->customer();
        $this->checkout($customer, $barang, 2);
        $sale = Sale::first();

        $this->actingAs($customer)->post("/penjualan/{$sale->id}/proses")->assertForbidden();
        $this->actingAs($customer)->post("/penjualan/{$sale->id}/bayar", ['payment_method' => 'cash'])->assertForbidden();
        $this->assertSame('pending', $sale->fresh()->status);
    }

    public function test_customer_can_download_own_invoice(): void
    {
        $barang = $this->product(10);
        $customer = $this->customer();
        $this->checkout($customer, $barang, 1);
        $sale = Sale::first();

        $response = $this->actingAs($customer)->get("/penjualan/{$sale->id}/invoice");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_customer_order_list_is_filterable_and_paginated(): void
    {
        $barang = $this->product(100);
        $customer = $this->customer();
        foreach (range(1, 12) as $i) {
            $this->checkout($customer, $barang, 1);
        }
        $first = Sale::orderBy('id')->first();
        $this->actingAs($customer)->post("/pesanan-saya/{$first->id}/batal");

        $this->actingAs($customer)->get('/pesanan-saya')->assertOk();
        $this->actingAs($customer)->get('/pesanan-saya?page=2')->assertOk();
        $cancelled = $this->actingAs($customer)->get('/pesanan-saya?status=cancelled');
        $this->assertSame(1, substr_count(substr($cancelled->getContent(), strpos($cancelled->getContent(), '<main')), 'SL-'));
    }
}
