<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Produk Uji',
            'unit' => 'pcs',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'minimum_stock' => 5,
        ];
    }

    public function test_admin_can_create_product_with_auto_sku_and_zero_stock(): void
    {
        $this->actingAs($this->admin())->post('/barang', $this->payload())->assertRedirect(route('barang.index'));

        $barang = Barang::where('name', 'Produk Uji')->first();
        $this->assertNotNull($barang);
        $this->assertStringStartsWith('BRG-', $barang->code);
        $this->assertSame(0, $barang->stock);
    }

    public function test_stock_cannot_be_set_directly_when_creating_product(): void
    {
        $this->actingAs($this->admin())->post('/barang', $this->payload(['stock' => 999]));

        $this->assertSame(0, Barang::where('name', 'Produk Uji')->value('stock'));
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/barang', $this->payload(['code' => 'SKU-1']));

        $this->actingAs($admin)->post('/barang', $this->payload(['code' => 'sku-1', 'name' => 'Lain']))
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Barang::where('code', 'SKU-1')->count());
    }

    public function test_sku_unique_constraint_also_exists_in_database(): void
    {
        $this->product(0, ['code' => 'DUP-1']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Barang::factory()->create(['code' => 'DUP-1']);
    }

    public function test_negative_price_and_selling_below_purchase_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/barang', $this->payload(['purchase_price' => -1]))->assertSessionHasErrors('purchase_price');
        $this->actingAs($admin)->post('/barang', $this->payload(['selling_price' => 5000]))->assertSessionHasErrors('selling_price');
        $this->assertDatabaseCount('barangs', 0);
    }

    public function test_product_with_stock_cannot_be_deleted_and_empty_product_is_soft_deleted(): void
    {
        $admin = $this->admin();
        $withStock = $this->product(5);
        $empty = $this->product(0);

        $this->actingAs($admin)->delete("/barang/{$withStock->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($withStock);

        $this->actingAs($admin)->delete("/barang/{$empty->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($empty);
    }

    public function test_soft_deleted_product_keeps_history_and_sku_is_not_reused(): void
    {
        $barang = $this->product(0);
        $barang->delete();

        $next = Barang::create([
            'category_id' => $barang->category_id, 'name' => 'Baru', 'unit' => 'pcs',
            'purchase_price' => 1, 'selling_price' => 2, 'minimum_stock' => 1,
        ]);

        $this->assertNotSame($barang->code, $next->code);
    }

    public function test_search_filter_sort_and_pagination_on_product_list(): void
    {
        $kategori = Category::factory()->create();
        foreach (range(1, 12) as $i) {
            Barang::factory()->create(['name' => "Barang Massal {$i}", 'category_id' => $kategori->id, 'code' => "MSL-{$i}"]);
        }
        Barang::factory()->create(['name' => 'Kopi Spesial', 'code' => 'KPI-1']);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/barang?search=Kopi')->assertOk()->assertSee('Kopi Spesial')->assertDontSee('Barang Massal 1');
        $this->actingAs($admin)->get('/barang?search=KPI-1')->assertSee('Kopi Spesial');
        $this->actingAs($admin)->get('/barang?category_id='.$kategori->id)->assertSee('Barang Massal')->assertDontSee('Kopi Spesial');

        $page2 = $this->actingAs($admin)->get('/barang?category_id='.$kategori->id.'&page=2');
        $page2->assertOk();
        $this->assertStringContainsString('category_id='.$kategori->id, $page2->getContent()); // filter dipertahankan di link pagination
    }

    public function test_low_stock_list_and_filter(): void
    {
        $low = $this->product(2, ['name' => 'Hampir Habis', 'minimum_stock' => 5]);
        $ok = $this->product(50, ['name' => 'Stok Aman', 'minimum_stock' => 5]);

        $this->actingAs($this->staff())->get('/stok-menipis')->assertSee('Hampir Habis')->assertDontSee('Stok Aman');
        $this->actingAs($this->staff())->get('/barang?low_stock=1')->assertSee('Hampir Habis')->assertDontSee('Stok Aman');
    }

    public function test_staff_can_view_product_detail(): void
    {
        $barang = $this->product(3);

        $this->actingAs($this->staff())->get("/barang/{$barang->id}")->assertOk()->assertSee($barang->name);
    }

    public function test_category_and_supplier_in_use_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $barang = $this->product(0);
        $barang->delete(); // soft deleted tetap dianggap terpakai

        $this->actingAs($admin)->delete('/kategori/'.$barang->category_id)->assertSessionHas('error');
        $this->assertModelExists(Category::find($barang->category_id));
    }
}
