<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PelangiSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_seeds_empty_database_once_without_overwriting_existing_data(): void
    {
        $this->artisan('pelangi:setup')->assertSuccessful();

        $this->assertSame(['owner', 'staff'], User::whereIn('email', ['owner@pelangi.test', 'staff@pelangi.test'])->orderBy('role')->pluck('role')->all());
        $count = Barang::count();
        $this->assertGreaterThanOrEqual(15, $count);

        // Stok setiap produk sama dengan jumlah di seluruh gudang dan dengan kartu stok.
        foreach (Barang::all() as $b) {
            $this->assertSame($b->stock, (int) $b->warehouseStocks()->sum('quantity'), "Stok gudang {$b->code}");
            $this->assertSame($b->stock, (int) $b->movements()->sum('quantity'), "Kartu stok {$b->code}");
        }

        $this->artisan('pelangi:setup')->assertSuccessful();
        $this->assertSame($count, Barang::count());
    }
}
