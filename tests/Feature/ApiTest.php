<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function bearer(User $user, string $type = 'mobile'): array
    {
        [, $plain] = ApiToken::issue($user, 'tes', $type);

        return ['Authorization' => "Bearer {$plain}"];
    }

    public function test_login_returns_token_and_wrong_password_is_rejected(): void
    {
        $user = User::factory()->pelanggan()->create(['email' => 'api@example.test']);

        $this->postJson('/api/v1/login', ['email' => 'api@example.test', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email', 'role']])
            ->assertJsonPath('user.role', 'pelanggan');

        $this->postJson('/api/v1/login', ['email' => 'api@example.test', 'password' => 'salah'])->assertStatus(422);
        $this->postJson('/api/v1/login', [])->assertStatus(422);
    }

    public function test_api_requires_valid_token(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->getJson('/api/v1/products', ['Authorization' => 'Bearer 1|ngawur'])->assertUnauthorized();
    }

    public function test_token_is_stored_hashed_and_logout_revokes_it(): void
    {
        $user = $this->customer();
        [$model, $plain] = ApiToken::issue($user, 'hp');

        $this->assertStringNotContainsString(explode('|', $plain)[1], $model->token);

        $headers = ['Authorization' => "Bearer {$plain}"];
        $this->getJson('/api/v1/me', $headers)->assertOk()->assertJsonPath('user.email', $user->email);
        $this->postJson('/api/v1/logout', [], $headers)->assertOk();
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        [, $plain] = ApiToken::issue($this->customer(), 'lama', 'mobile', now()->subMinute());

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$plain}"])->assertUnauthorized();
    }

    public function test_products_endpoint_lists_active_products(): void
    {
        $this->product(5, ['name' => 'Kopi Aceh Gayo']);

        $this->getJson('/api/v1/products', $this->bearer($this->customer()))->assertOk()->assertJsonFragment(['name' => 'Kopi Aceh Gayo']);
    }

    public function test_customer_can_create_and_cancel_order_via_api(): void
    {
        $a = $this->product(10, ['selling_price' => 20000]);
        $customer = $this->customer();
        $headers = $this->bearer($customer);

        $response = $this->postJson('/api/v1/orders', [
            'shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe',
            'items' => [['barang_id' => $a->id, 'quantity' => 2, 'unit_price' => 1]],
        ], $headers)->assertCreated();

        $response->assertJsonPath('data.total', 40000)->assertJsonPath('data.status', 'pending');
        $this->assertSame(10, $a->fresh()->stock); // pesanan pending belum memotong stok

        $number = $response->json('data.sale_number');
        $sale = \App\Models\Sale::where('sale_number', $number)->first();

        $this->postJson("/api/v1/orders/{$sale->id}/cancel", [], $headers)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(10, $a->fresh()->stock);
    }

    public function test_api_order_with_too_much_quantity_is_rejected(): void
    {
        $a = $this->product(1);

        $this->postJson('/api/v1/orders', [
            'shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe',
            'items' => [['barang_id' => $a->id, 'quantity' => 5]],
        ], $this->bearer($this->customer()))->assertStatus(422);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_api_order_validation(): void
    {
        $this->postJson('/api/v1/orders', ['items' => []], $this->bearer($this->customer()))->assertStatus(422)->assertJsonValidationErrors(['shipping_address', 'items']);
    }

    public function test_staff_and_admin_cannot_place_orders_via_api(): void
    {
        $a = $this->product(5);
        $payload = ['shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe', 'items' => [['barang_id' => $a->id, 'quantity' => 1]]];

        $this->postJson('/api/v1/orders', $payload, $this->bearer($this->staff()))->assertForbidden();
        $this->postJson('/api/v1/orders', $payload, $this->bearer($this->admin()))->assertForbidden();
    }

    public function test_customer_cannot_read_or_cancel_other_customers_order_via_api(): void
    {
        $a = $this->product(5);
        $owner = $this->customer();
        $order = app(\App\Services\SaleService::class)->createOrder($owner, ['shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe', 'items' => [['barang_id' => $a->id, 'quantity' => 1]]]);

        $other = $this->bearer($this->customer());
        $this->getJson("/api/v1/orders/{$order->id}", $other)->assertForbidden();
        $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $other)->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_orders_list_only_contains_own_orders_for_customers(): void
    {
        $a = $this->product(10);
        $mine = $this->customer();
        $svc = app(\App\Services\SaleService::class);
        $payload = ['shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe', 'items' => [['barang_id' => $a->id, 'quantity' => 1]]];
        $own = $svc->createOrder($mine, $payload);
        $svc->createOrder($this->customer(), $payload);

        $res = $this->getJson('/api/v1/orders', $this->bearer($mine))->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame($own->sale_number, $res->json('data.0.sale_number'));
    }

    public function test_internal_dashboard_endpoint_is_denied_to_customers(): void
    {
        $this->getJson('/api/v1/dashboard', $this->bearer($this->customer()))->assertForbidden();
        $this->getJson('/api/v1/dashboard', $this->bearer($this->staff()))->assertOk();
    }

    public function test_erp_api_needs_erp_token_owned_by_admin(): void
    {
        $this->product(3);
        $admin = $this->admin();

        $this->getJson('/api/erp/v1/products', $this->bearer($admin, 'erp'))->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->getJson('/api/erp/v1/sales', $this->bearer($admin, 'erp'))->assertOk();
        $this->getJson('/api/erp/v1/purchases', $this->bearer($admin, 'erp'))->assertOk();

        // Token mobile tidak boleh dipakai di API ERP.
        $this->getJson('/api/erp/v1/products', $this->bearer($admin, 'mobile'))->assertUnauthorized();
        // Token ERP milik staff/pelanggan ditolak oleh gate.
        $this->getJson('/api/erp/v1/products', $this->bearer($this->staff(), 'erp'))->assertForbidden();
        $this->getJson('/api/erp/v1/products', $this->bearer($this->customer(), 'erp'))->assertForbidden();
        $this->getJson('/api/erp/v1/products')->assertUnauthorized();
    }

    public function test_admin_can_create_and_revoke_api_keys(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/integrasi/token', ['name' => 'ERP Pusat', 'type' => 'erp'])->assertSessionHas('new_token');
        $token = ApiToken::first();
        $this->assertSame('erp', $token->type);

        $this->actingAs($admin)->delete("/integrasi/token/{$token->id}");
        $this->assertDatabaseCount('api_tokens', 0);

        $this->actingAs($this->staff())->post('/integrasi/token', ['name' => 'x', 'type' => 'erp'])->assertForbidden();
    }
}
