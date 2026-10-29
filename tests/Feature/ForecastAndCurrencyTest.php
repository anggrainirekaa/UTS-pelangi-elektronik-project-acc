<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Sale;
use App\Services\CurrencyService;
use App\Services\ForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class ForecastAndCurrencyTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    public function test_linear_regression_fits_known_lines(): void
    {
        $svc = new ForecastService();

        [$a, $b] = $svc->linearRegression([2, 4, 6, 8]);
        $this->assertEqualsWithDelta(2.0, $a, 1e-9);
        $this->assertEqualsWithDelta(2.0, $b, 1e-9);

        [$a, $b] = $svc->linearRegression([5, 5, 5, 5]);
        $this->assertEqualsWithDelta(5.0, $a, 1e-9);
        $this->assertEqualsWithDelta(0.0, $b, 1e-9);

        $this->assertSame([0.0, 0.0], $svc->linearRegression([]));
        $this->assertSame([7.0, 0.0], $svc->linearRegression([7]));
    }

    public function test_forecast_with_no_sales_has_no_demand_and_no_reorder_beyond_minimum(): void
    {
        $this->product(20, ['minimum_stock' => 5]);

        $row = app(ForecastService::class)->forecast()->first();

        $this->assertSame(0, $row['predicted_demand']);
        $this->assertSame('tidak ada permintaan', $row['status']);
        $this->assertSame(0, $row['reorder_qty']);
    }

    public function test_forecast_suggests_reorder_when_demand_exceeds_stock(): void
    {
        $a = $this->product(100, ['minimum_stock' => 5]);
        $staff = $this->staff();
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($staff)->post('/penjualan', ['warehouse_id' => \App\Models\Warehouse::default()->id, 'items' => [['barang_id' => $a->id, 'quantity' => 12]]]);
        }
        $this->assertSame(28, $a->fresh()->stock);

        $row = app(ForecastService::class)->forecast()->firstWhere(fn ($r) => $r['barang']->id === $a->id);

        $this->assertSame(72, $row['sold_history']);
        $this->assertGreaterThan(0, $row['predicted_demand']);
        $this->assertSame(max(0, $row['predicted_demand'] + 5 - 28), $row['reorder_qty']);
    }

    public function test_forecast_page_is_for_staff_and_admin_only(): void
    {
        $this->actingAs($this->admin())->get('/prediksi-stok')->assertOk();
        $this->actingAs($this->staff())->get('/prediksi-stok')->assertOk();
        $this->actingAs($this->customer())->get('/prediksi-stok')->assertForbidden();
    }

    public function test_currency_conversion_is_display_only(): void
    {
        Currency::updateOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'rate' => 16000, 'is_base' => false, 'is_active' => true]);
        $usd = Currency::where('code', 'USD')->first();
        $svc = app(CurrencyService::class);

        $this->assertEqualsWithDelta(10.0, $svc->convert(160000, $usd), 1e-9);
        $this->assertSame('$ 10.00', $svc->format(160000, $usd));
        $this->assertSame('Rp 160.000', $svc->formatBase(160000));
    }

    public function test_user_can_pick_currency_but_stored_amounts_stay_in_idr(): void
    {
        Currency::updateOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'rate' => 16000, 'is_base' => false, 'is_active' => true]);
        $a = $this->product(10, ['selling_price' => 32000]);
        $staff = $this->staff();

        $this->actingAs($staff)->post('/mata-uang/pilih', ['code' => 'USD'])->assertSessionHas('currency', 'USD');
        $this->actingAs($staff)->post('/penjualan', ['warehouse_id' => \App\Models\Warehouse::default()->id, 'items' => [['barang_id' => $a->id, 'quantity' => 1]]]);

        $this->assertSame(32000, Sale::first()->total); // tetap IDR di database
        $this->actingAs($staff)->get('/barang')->assertSee('$ 2.00');
    }

    public function test_unknown_or_inactive_currency_cannot_be_selected(): void
    {
        Currency::where('code', 'EUR')->update(['is_active' => false]);
        $user = $this->customer();

        // Kode tak dikenal / nonaktif diabaikan: tampilan tetap IDR.
        $this->actingAs($user)->post('/mata-uang/pilih', ['code' => 'XXX'])->assertSessionDoesntHaveErrors();
        $this->actingAs($user)->post('/mata-uang/pilih', ['code' => 'EUR']);
        $this->assertNull(session('currency'));
        $this->actingAs($user)->post('/mata-uang/pilih', ['code' => 'ab'])->assertSessionHasErrors('code');
    }

    public function test_only_admin_manages_currencies(): void
    {
        $this->actingAs($this->admin())->get('/mata-uang')->assertOk();
        $this->actingAs($this->staff())->get('/mata-uang')->assertForbidden();
        $this->actingAs($this->customer())->get('/mata-uang')->assertForbidden();
    }
}
