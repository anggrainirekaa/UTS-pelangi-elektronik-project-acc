<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/** Tepat tiga role: Admin/Owner, Staff, Pelanggan. Otorisasi ditegakkan di server. */
class RoleAccessTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    public function test_exactly_three_roles_exist(): void
    {
        $this->assertSame(['owner', 'staff', 'pelanggan'], User::ROLES);
    }

    public function test_admin_owner_can_open_all_management_pages(): void
    {
        $admin = $this->admin();
        foreach (['/dashboard', '/pengguna', '/kategori', '/supplier', '/barang', '/pembelian', '/penjualan', '/laporan/penjualan', '/laporan/laba-rugi', '/stok-menipis', '/audit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_staff_cannot_reach_admin_functions_by_direct_url(): void
    {
        $staff = $this->staff();
        foreach (['/pengguna', '/laporan/penjualan', '/laporan/laba-rugi', '/audit', '/kategori/create', '/supplier/create', '/barang/create'] as $url) {
            $this->actingAs($staff)->get($url)->assertForbidden();
        }
        foreach (['/barang', '/pembelian', '/penjualan', '/dashboard', '/stok-menipis'] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }

    public function test_pelanggan_has_no_admin_or_internal_access(): void
    {
        $c = $this->customer();
        foreach (['/pengguna', '/barang', '/pembelian', '/penjualan', '/laporan/penjualan', '/kategori'] as $url) {
            $this->actingAs($c)->get($url)->assertForbidden();
        }
        $this->actingAs($c)->get('/katalog')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_role_is_rejected_by_user_validation(): void
    {
        $this->actingAs($this->admin())->post('/pengguna', ['name' => 'X', 'email' => 'x@pelangi.test', 'role' => 'admin', 'password' => 'password-kuat-1', 'password_confirmation' => 'password-kuat-1'])->assertSessionHasErrors('role');
    }
}
