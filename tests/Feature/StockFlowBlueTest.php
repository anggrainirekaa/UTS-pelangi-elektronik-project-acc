<?php

namespace Tests\Feature;

use App\Models\AnomalyFlag;
use App\Models\Barang;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\AnomalyDetector;
use App\Services\RestockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class StockFlowBlueTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function draftPo(User $user, Barang $b, int $qty, array $extra = [])
    {
        return $this->actingAs($user)->post('/pembelian', $extra + [
            'mode' => 'draft', 'supplier_id' => $this->supplier()->id, 'warehouse_id' => Warehouse::default()->id,
            'items' => [['barang_id' => $b->id, 'quantity' => $qty, 'unit_cost' => 5000]],
        ]);
    }

    private function approvedPo(Barang $b, int $qty): Purchase
    {
        $this->draftPo($this->staff(), $b, $qty);
        $po = Purchase::latest('id')->first();
        $this->actingAs($po->user)->post("/pembelian/{$po->id}/ajukan");
        $this->actingAs($this->admin())->post("/pembelian/{$po->id}/setujui");

        return $po->fresh();
    }

    private function pos(User $user, Barang $b, int $qty, array $extra = [])
    {
        return $this->actingAs($user)->post('/penjualan', $extra + [
            'warehouse_id' => Warehouse::default()->id, 'payment_method' => 'cash', 'mark_paid' => 1,
            'items' => [['barang_id' => $b->id, 'quantity' => $qty]],
        ]);
    }

    // ---------------------------------------------------------------- Pembelian & penerimaan

    public function test_draft_po_does_not_change_stock_until_approved_and_received(): void
    {
        $b = $this->product(0);
        $staff = $this->staff();
        $this->draftPo($staff, $b, 30);
        $po = Purchase::first();

        $this->assertSame('draft', $po->status);
        $this->assertSame(0, $b->fresh()->stock);

        $this->actingAs($staff)->post("/pembelian/{$po->id}/ajukan");
        $this->assertSame('pending', $po->fresh()->status);

        // Staff tidak boleh menyetujui.
        $this->actingAs($staff)->post("/pembelian/{$po->id}/setujui")->assertForbidden();
        $this->assertSame('pending', $po->fresh()->status);

        // Belum disetujui: tidak bisa menerima barang.
        $this->actingAs($staff)->get("/pembelian/{$po->id}/terima")->assertRedirect();

        $this->actingAs($this->admin())->post("/pembelian/{$po->id}/setujui");
        $this->assertSame('approved', $po->fresh()->status);
        $this->assertSame(0, $b->fresh()->stock);
    }

    public function test_partial_then_full_receipt_updates_stock_and_status(): void
    {
        $b = $this->product(0);
        $po = $this->approvedPo($b, 30);
        $item = $po->items()->first();

        $this->actingAs($po->user)->post("/pembelian/{$po->id}/terima", ['lines' => [['purchase_item_id' => $item->id, 'quantity' => 10]]]);
        $this->assertSame(10, $b->fresh()->stock);
        $this->assertSame('partially_received', $po->fresh()->status);

        // Melebihi sisa ditolak dan stok tidak berubah.
        $this->actingAs($po->user)->post("/pembelian/{$po->id}/terima", ['lines' => [['purchase_item_id' => $item->id, 'quantity' => 25]]])->assertSessionHas('error');
        $this->assertSame(10, $b->fresh()->stock);

        $this->actingAs($po->user)->post("/pembelian/{$po->id}/terima", ['lines' => [['purchase_item_id' => $item->id, 'quantity' => 20]]]);
        $this->assertSame(30, $b->fresh()->stock);
        $this->assertSame('completed', $po->fresh()->status);
    }

    public function test_expiry_product_requires_expiry_date_and_sells_fefo(): void
    {
        $b = $this->product(0, ['tracks_expiry' => true, 'expiry_warning_days' => 30]);
        $po = $this->approvedPo($b, 20);
        $item = $po->items()->first();

        $this->actingAs($po->user)->post("/pembelian/{$po->id}/terima", ['lines' => [['purchase_item_id' => $item->id, 'quantity' => 10]]])->assertSessionHasErrors('items');
        $this->assertSame(0, $b->fresh()->stock);

        $this->actingAs($po->user)->post("/pembelian/{$po->id}/terima", ['lines' => [['purchase_item_id' => $item->id, 'quantity' => 10, 'batch_number' => 'LATE', 'expiry_date' => now()->addDays(200)->toDateString()]]]);
        $this->actingAs($po->user)->post("/pembelian/{$po->id}/terima", ['lines' => [['purchase_item_id' => $item->id, 'quantity' => 10, 'batch_number' => 'SOON', 'expiry_date' => now()->addDays(20)->toDateString()]]]);

        $this->pos($this->staff(), $b, 4);

        $this->assertSame(6, StockBatch::where('batch_number', 'SOON')->value('quantity'));
        $this->assertSame(10, StockBatch::where('batch_number', 'LATE')->value('quantity'));
    }

    public function test_expired_batch_is_not_sellable(): void
    {
        $b = $this->product(0, ['tracks_expiry' => true]);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\StockService::class)->increase($b->id, Warehouse::default()->id, 5, ['batch_number' => 'OLD', 'expiry_date' => now()->addDays(5)->toDateString()]));
        $this->assertSame(1, StockBatch::count());
        StockBatch::query()->update(['expiry_date' => now()->subDay()->toDateString()]);

        $this->pos($this->staff(), $b, 1)->assertSessionHasErrors('items');
        $this->assertSame(5, $b->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    // ---------------------------------------------------------------- Penjualan

    public function test_double_submit_with_same_request_key_creates_one_sale(): void
    {
        $b = $this->product(10);
        $staff = $this->staff();
        $key = (string) Str::uuid();

        $this->pos($staff, $b, 3, ['request_key' => $key]);
        $this->pos($staff, $b, 3, ['request_key' => $key]);

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(7, $b->fresh()->stock);
    }

    public function test_staff_discount_is_capped_but_admin_is_not(): void
    {
        $b = $this->product(20, ['selling_price' => 10000]);

        $this->pos($this->staff(), $b, 2, ['discount_type' => 'percent', 'discount_value' => 50])->assertSessionHasErrors('discount_value');
        $this->assertDatabaseCount('sales', 0);

        $this->pos($this->staff(), $b, 2, ['discount_type' => 'percent', 'discount_value' => 10]);
        $this->assertSame(18000, Sale::first()->total);
        $this->assertSame(2000, Sale::first()->discount);

        $this->pos($this->admin(), $b, 2, ['discount_type' => 'percent', 'discount_value' => 50]);
        $this->assertSame(10000, Sale::latest('id')->first()->total);
    }

    public function test_return_restores_stock_and_rejects_over_return(): void
    {
        $b = $this->product(10);
        $staff = $this->staff();
        $this->pos($staff, $b, 4);
        $sale = Sale::first();
        $item = $sale->items()->first();

        $this->actingAs($staff)->post("/penjualan/{$sale->id}/retur", ['reason' => '', 'items' => [['sale_item_id' => $item->id, 'quantity' => 1]]])->assertSessionHasErrors('reason');

        $this->actingAs($staff)->post("/penjualan/{$sale->id}/retur", ['reason' => 'Rusak', 'items' => [['sale_item_id' => $item->id, 'quantity' => 3]]])->assertSessionHas('success');
        $this->assertSame(9, $b->fresh()->stock);

        $this->actingAs($staff)->post("/penjualan/{$sale->id}/retur", ['reason' => 'Lagi', 'items' => [['sale_item_id' => $item->id, 'quantity' => 2]]])->assertSessionHas('error');
        $this->assertSame(9, $b->fresh()->stock);
        $this->assertDatabaseCount('sale_returns', 1);
    }

    public function test_pelanggan_cannot_access_internal_pages(): void
    {
        $c = $this->customer();
        foreach (['/opname', '/restock', '/analitik', '/kedaluwarsa', '/anomali', '/audit', '/laporan/otomatis', '/dashboard/data'] as $url) {
            $this->actingAs($c)->get($url)->assertForbidden();
        }
    }

    public function test_staff_cannot_open_admin_only_pages(): void
    {
        $s = $this->staff();
        foreach (['/anomali', '/audit', '/laporan/otomatis'] as $url) {
            $this->actingAs($s)->get($url)->assertForbidden();
        }
        $this->actingAs($s)->post('/barang/massal', ['action' => 'deactivate', 'ids' => [1]])->assertForbidden();
    }

    // ---------------------------------------------------------------- Transfer

    public function test_large_staff_transfer_needs_admin_and_moves_stock_once(): void
    {
        $from = Warehouse::default();
        $to = Warehouse::create(['code' => 'GD2', 'name' => 'Gudang 2']);
        $b = $this->product(500);
        $staff = $this->staff();

        $this->actingAs($staff)->post('/transfer-stok', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'barang_id' => $b->id, 'quantity' => 200]);
        $t = StockTransfer::first();
        $this->assertSame('pending', $t->status);
        $this->assertSame(500, $this->warehouseStock($b, $from));

        $this->actingAs($staff)->post("/transfer-stok/{$t->id}/setujui")->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->post("/transfer-stok/{$t->id}/setujui");
        $this->actingAs($admin)->post("/transfer-stok/{$t->id}/setujui")->assertSessionHas('error');

        $this->assertSame(300, $this->warehouseStock($b, $from));
        $this->assertSame(200, $this->warehouseStock($b, $to));
        $this->assertSame(500, $b->fresh()->stock);
    }

    public function test_small_transfer_is_immediate_and_rejection_needs_reason(): void
    {
        $from = Warehouse::default();
        $to = Warehouse::create(['code' => 'GD3', 'name' => 'Gudang 3']);
        $b = $this->product(50);

        $this->actingAs($this->staff())->post('/transfer-stok', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'barang_id' => $b->id, 'quantity' => 10]);
        $this->assertSame('completed', StockTransfer::first()->status);
        $this->assertSame(10, $this->warehouseStock($b, $to));

        $b2 = $this->product(500);
        $this->actingAs($this->staff())->post('/transfer-stok', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'barang_id' => $b2->id, 'quantity' => 300]);
        $t = StockTransfer::latest('id')->first();
        $this->actingAs($this->admin())->post("/transfer-stok/{$t->id}/tolak", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin())->post("/transfer-stok/{$t->id}/tolak", ['reason' => 'Tidak perlu']);
        $this->assertSame('rejected', $t->fresh()->status);
        $this->assertSame(500, $this->warehouseStock($b2, $from));
    }

    // ---------------------------------------------------------------- Opname

    public function test_opname_changes_stock_only_after_admin_approval(): void
    {
        $b = $this->product(20);
        $staff = $this->staff();
        $wh = Warehouse::default();

        $this->actingAs($staff)->post('/opname', ['warehouse_id' => $wh->id, 'barang_ids' => [$b->id]]);
        $op = StockOpname::first();

        $this->actingAs($staff)->put("/opname/{$op->id}/hitung", ['counts' => [$b->id => ['counted' => 17, 'reason' => 'Rusak']]]);
        $this->actingAs($staff)->post("/opname/{$op->id}/ajukan");
        $this->assertSame('submitted', $op->fresh()->status);
        $this->assertSame(20, $b->fresh()->stock);

        $this->actingAs($staff)->post("/opname/{$op->id}/setujui")->assertForbidden();
        $this->assertSame(20, $b->fresh()->stock);

        $this->actingAs($this->admin())->post("/opname/{$op->id}/setujui");
        $this->assertSame(17, $b->fresh()->stock);
        $this->assertSame('approved', $op->fresh()->status);

        // Persetujuan kedua tidak menggandakan penyesuaian.
        $this->actingAs($this->admin())->post("/opname/{$op->id}/setujui");
        $this->assertSame(17, $b->fresh()->stock);
    }

    public function test_opname_difference_requires_reason(): void
    {
        $b = $this->product(20);
        $staff = $this->staff();
        $this->actingAs($staff)->post('/opname', ['warehouse_id' => Warehouse::default()->id, 'barang_ids' => [$b->id]]);
        $op = StockOpname::first();

        $this->actingAs($staff)->put("/opname/{$op->id}/hitung", ['counts' => [$b->id => ['counted' => 15, 'reason' => '']]]);
        $this->actingAs($staff)->post("/opname/{$op->id}/ajukan");

        $this->assertSame('draft', $op->fresh()->status);
    }

    // ---------------------------------------------------------------- Anomali, restock, notifikasi

    public function test_anomaly_scan_flags_but_never_changes_data(): void
    {
        $b = $this->product(10);
        WarehouseStock::where('barang_id', $b->id)->update(['quantity' => 3]); // saldo gudang tidak cocok dengan kartu stok

        $new = app(AnomalyDetector::class)->scan();

        $this->assertGreaterThan(0, $new);
        $this->assertTrue(AnomalyFlag::where('status', 'open')->exists());
        $this->assertSame(3, WarehouseStock::where('barang_id', $b->id)->value('quantity'));

        $flag = AnomalyFlag::first();
        $this->actingAs($this->admin())->patch("/anomali/{$flag->id}", ['status' => 'reviewed', 'note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($this->admin())->patch("/anomali/{$flag->id}", ['status' => 'reviewed', 'note' => 'Sudah dicek']);
        $this->assertSame('reviewed', $flag->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'anomaly.reviewed']);
    }

    public function test_restock_creates_draft_po_only(): void
    {
        $sup = $this->supplier();
        $b = $this->product(2, ['supplier_id' => $sup->id, 'minimum_stock' => 10]);

        $rows = app(RestockService::class)->recommendations();
        $this->assertTrue($rows->contains(fn ($r) => $r['barang']->id === $b->id));
        $this->assertNotEmpty($rows->first()['reason']);

        $this->actingAs($this->staff())->post('/restock/draft-po', ['barang_ids' => [$b->id]])->assertRedirect();
        $po = Purchase::first();
        $this->assertSame('draft', $po->status);
        $this->assertSame(2, $b->fresh()->stock);
        $this->actingAs($this->staff())->get('/restock')->assertOk();
    }

    public function test_low_stock_creates_notification_and_open_marks_read(): void
    {
        $admin = $this->admin();
        $b = $this->product(10, ['minimum_stock' => 5]);

        $this->pos($this->staff(), $b, 8);

        $this->assertGreaterThan(0, $admin->unreadNotifications()->count());
        $n = $admin->notifications()->first();

        $this->actingAs($admin)->get("/notifikasi/{$n->id}/buka")->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);

        // Notifikasi milik orang lain tidak dapat dibuka.
        $this->actingAs($this->customer())->get("/notifikasi/{$n->id}/buka")->assertNotFound();

        $this->actingAs($admin)->post('/notifikasi/baca-semua');
        $this->assertSame(0, $admin->unreadNotifications()->count());
        $this->actingAs($admin)->get('/notifikasi')->assertOk();
    }

    // ---------------------------------------------------------------- Pencarian, dashboard, laporan

    public function test_search_matches_barcode_sku_category_and_hides_internal_groups_from_customers(): void
    {
        $b = $this->product(5, ['barcode' => '8991234567890', 'name' => 'Teh Hijau']);

        $json = $this->actingAs($this->staff())->getJson('/pencarian?q=8991234567890')->assertOk()->json();
        $this->assertSame('Teh Hijau', $json['groups'][0]['items'][0]['title']);

        $this->actingAs($this->staff())->getJson('/pencarian?q='.$b->code)->assertOk()->assertJsonPath('groups.0.title', 'Produk');

        $cust = $this->actingAs($this->customer())->getJson('/pencarian?q=Teh')->assertOk()->json();
        $this->assertSame(['Produk'], collect($cust['groups'])->pluck('title')->all());

        $this->actingAs($this->staff())->getJson('/pencarian?q=a')->assertOk()->assertJsonPath('groups', []);
    }

    public function test_dashboard_data_and_pages_render_with_real_numbers(): void
    {
        $b = $this->product(30, ['selling_price' => 10000]);
        $this->pos($this->staff(), $b, 3);
        $admin = $this->admin();

        $json = $this->actingAs($admin)->getJson('/dashboard/data?preset=7d')->assertOk()->json();
        $this->assertSame(30000, array_sum($json['sales']['values']));
        $this->assertSame(3, $json['top'][0]['qty']);

        foreach (['/dashboard', '/analitik', '/analitik?preset=12m', '/kedaluwarsa', '/restock', '/opname', '/opname/create', '/anomali', '/audit', '/laporan/otomatis', '/barang?view=grid', '/barang?status=low', '/penjualan/create', '/pembelian/create', '/transfer-stok', '/pembelian'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_dashboard_without_data_shows_no_fake_percentages(): void
    {
        $r = $this->actingAs($this->admin())->get('/dashboard')->assertOk();
        $r->assertSee('Belum cukup data');
        $this->assertNull($r->viewData('stats')['sales']['pct']);
    }

    public function test_auto_report_csv_export_and_formula_safety(): void
    {
        $b = $this->product(10, ['name' => '=CMD()']);
        $this->pos($this->staff(), $b, 1);

        $res = $this->actingAs($this->admin())->get('/laporan/otomatis/ekspor?period=monthly&format=csv');
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString('Penjualan bersih', $body);
        $this->assertStringContainsString("'=CMD()", $body);
    }

    // ---------------------------------------------------------------- Aksi massal & halaman error

    public function test_bulk_actions_skip_products_with_stock_on_delete(): void
    {
        $admin = $this->admin();
        $a = $this->product(0);
        $b = $this->product(5);

        $this->actingAs($admin)->post('/barang/massal', ['action' => 'deactivate', 'ids' => [$a->id, $b->id]]);
        $this->assertFalse($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);

        $this->actingAs($admin)->post('/barang/massal', ['action' => 'delete', 'ids' => [$a->id, $b->id]]);
        $this->assertSoftDeleted($a);
        $this->assertNotSoftDeleted($b);

        $this->actingAs($admin)->post('/barang/massal', ['action' => 'delete', 'ids' => []])->assertSessionHasErrors('ids');
    }

    public function test_error_pages_are_friendly_without_stack_traces(): void
    {
        $this->actingAs($this->admin())->get('/halaman-tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertDontSee('Stack trace');
        $this->actingAs($this->customer())->get('/barang')->assertForbidden()->assertSee('Akses ditolak');
    }
}
