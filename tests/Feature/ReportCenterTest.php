<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class ReportCenterTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function sell($user, $barang, int $qty): Sale
    {
        $this->actingAs($user)->post('/penjualan', ['warehouse_id' => Warehouse::default()->id, 'items' => [['barang_id' => $barang->id, 'quantity' => $qty]]])->assertRedirect();

        return Sale::latest('id')->first();
    }

    public function test_every_report_type_opens_for_admin_even_without_data(): void
    {
        $admin = $this->admin();
        foreach (['penjualan', 'pembelian', 'stok', 'stok-rendah', 'pergerakan-stok', 'produk-terlaris', 'pendapatan'] as $type) {
            $this->actingAs($admin)->get("/laporan/data/{$type}")->assertOk()->assertSee('Tidak ada data', false);
        }
        $this->actingAs($admin)->get('/laporan/data/tidak-ada')->assertNotFound();
    }

    public function test_sales_report_respects_the_period_filter(): void
    {
        $b = $this->product(20, ['selling_price' => 10000]);
        $this->sell($this->staff(), $b, 2);
        $old = $this->sell($this->staff(), $b, 3);
        $old->update(['sold_at' => now()->subDays(40)]);
        $admin = $this->admin();
        $this->flushSession(); // hilangkan pesan flash "berhasil" yang memuat nomor transaksi

        $this->actingAs($admin)->get('/laporan/data/penjualan?preset=7d')->assertOk()->assertSee('Rp 20.000')->assertDontSee($old->sale_number);
        $this->actingAs($admin)->get('/laporan/data/penjualan?start_date='.now()->subDays(60)->toDateString().'&end_date='.now()->toDateString())->assertOk()->assertSee($old->sale_number)->assertSee('Rp 50.000');
        $this->actingAs($admin)->get('/laporan/data/penjualan?preset=3d')->assertOk()->assertDontSee($old->sale_number);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->actingAs($this->admin())->get('/laporan/data/penjualan?start_date=2026-05-10&end_date=2026-05-01')->assertSessionHasErrors('end_date');
    }

    public function test_low_stock_and_stock_reports_list_products(): void
    {
        $low = $this->product(2, ['name' => 'Barang Hampir Habis']);
        $ok = $this->product(50, ['name' => 'Barang Aman Sekali']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/laporan/data/stok-rendah')->assertOk()->assertSee('Barang Hampir Habis')->assertDontSee('Barang Aman Sekali');
        $this->actingAs($admin)->get('/laporan/data/stok')->assertOk()->assertSee('Barang Aman Sekali');
        $cat = Category::factory()->create();
        $this->actingAs($admin)->get('/laporan/data/stok?category_id='.$cat->id)->assertOk()->assertDontSee('Barang Aman Sekali');
    }

    public function test_top_products_and_revenue_reports_use_real_sales(): void
    {
        $b = $this->product(30, ['name' => 'Produk Laris Uji', 'selling_price' => 20000, 'purchase_price' => 12000]);
        $this->sell($this->staff(), $b, 4);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/laporan/data/produk-terlaris')->assertOk()->assertSee('Produk Laris Uji')->assertSee('Rp 80.000')->assertSee('Rp 32.000');
        $this->actingAs($admin)->get('/laporan/data/pendapatan')->assertOk()->assertSee('Rp 80.000');
        $this->actingAs($admin)->get('/laporan/data/pergerakan-stok')->assertOk()->assertSee('Produk Laris Uji');
    }

    public function test_csv_export_is_safe_against_formula_injection(): void
    {
        $this->product(5, ['name' => '=HYPERLINK("http://x")']);
        $res = $this->actingAs($this->admin())->get('/laporan/data/stok/csv');
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_reports_are_forbidden_for_staff_and_customer(): void
    {
        foreach ([$this->staff(), $this->customer()] as $u) {
            $this->actingAs($u)->get('/laporan/data/stok')->assertForbidden();
            $this->actingAs($u)->get('/laporan/data/stok/csv')->assertForbidden();
        }
    }

    public function test_dashboard_period_filter_and_kpis(): void
    {
        $b = $this->product(30, ['selling_price' => 10000]);
        $this->sell($this->staff(), $b, 2);
        $old = $this->sell($this->staff(), $b, 5);
        $old->update(['sold_at' => now()->subDays(20)]);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/dashboard?preset=3d')->assertOk()->assertSee('Rp 20.000');
        $this->actingAs($admin)->get('/dashboard?preset=30d')->assertOk()->assertSee('Rp 70.000');
        $this->actingAs($admin)->get('/dashboard?preset=12m')->assertOk();
        $this->actingAs($admin)->get('/dashboard?from='.now()->subDays(2)->toDateString().'&to='.now()->toDateString())->assertOk()->assertSee('Rp 20.000');
        $this->actingAs($admin)->get('/dashboard?preset=999x')->assertSessionHasErrors('preset');

        $json = $this->actingAs($admin)->getJson('/dashboard/data?preset=7d')->assertOk()->json();
        $this->assertArrayHasKey('purchases', $json);
        $this->assertSame(7, count($json['sales']['labels']));
        $this->assertSame(20000, array_sum($json['sales']['values']));
        $this->assertSame(3, count($this->actingAs($admin)->getJson('/dashboard/data?preset=3d')->json('sales.labels')));
    }

    public function test_category_and_supplier_status_and_supplier_detail(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/kategori', ['name' => 'Kategori Nonaktif', 'is_active' => 0])->assertRedirect();
        $this->assertFalse(Category::where('name', 'Kategori Nonaktif')->first()->is_active);

        $s = Supplier::factory()->create(['is_active' => false, 'name' => 'Pemasok Tidur']);
        $this->actingAs($admin)->get('/supplier?status=inactive')->assertOk()->assertSee('Pemasok Tidur');
        $this->actingAs($admin)->get('/supplier?status=active')->assertOk()->assertDontSee('Pemasok Tidur');
        $this->actingAs($admin)->get('/supplier/'.$s->id)->assertOk()->assertSee('Pemasok Tidur');
        $this->actingAs($this->staff())->get('/supplier/'.$s->id)->assertForbidden();
    }
}
