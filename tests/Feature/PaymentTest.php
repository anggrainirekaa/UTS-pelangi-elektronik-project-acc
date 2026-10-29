<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function order($customer, $barang, int $qty = 1): Sale
    {
        return app(SaleService::class)->createOrder($customer, [
            'shipping_address' => 'Jl. Contoh No. 1, Lhokseumawe',
            'items' => [['barang_id' => $barang->id, 'quantity' => $qty]],
        ]);
    }

    public function test_customer_can_pay_order_with_simulated_gateway(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $this->product(10));

        $this->actingAs($customer)->post("/pesanan-saya/{$order->id}/bayar", ['payment_method' => 'qris'])
            ->assertSessionHas('success');

        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_card_ending_0000_is_declined_and_order_stays_unpaid(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $this->product(10));

        $this->actingAs($customer)->post("/pesanan-saya/{$order->id}/bayar", ['payment_method' => 'kartu', 'card_number' => '4111 1111 1111 0000'])
            ->assertSessionHas('error');

        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_unknown_payment_method_is_rejected_by_validation(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $this->product(10));

        $this->actingAs($customer)->post("/pesanan-saya/{$order->id}/bayar", ['payment_method' => 'bitcoin'])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_customer_cannot_pay_someone_elses_order(): void
    {
        $order = $this->order($this->customer(), $this->product(10));

        $this->actingAs($this->customer())->post("/pesanan-saya/{$order->id}/bayar", ['payment_method' => 'qris'])->assertForbidden();
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_paid_order_cannot_be_paid_twice(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $this->product(10));

        $this->actingAs($customer)->post("/pesanan-saya/{$order->id}/bayar", ['payment_method' => 'qris']);
        $this->actingAs($customer)->post("/pesanan-saya/{$order->id}/bayar", ['payment_method' => 'qris'])->assertSessionHas('error');
    }

    public function test_webhook_with_valid_signature_marks_sale_paid_and_is_idempotent(): void
    {
        config(['services.payment.webhook_secret' => 'rahasia-tes']);
        $customer = $this->customer();
        $order = $this->order($customer, $this->product(10));

        $body = json_encode(['sale_number' => $order->sale_number, 'status' => 'paid', 'reference' => 'REF-1', 'method' => 'transfer_bank']);
        $signature = hash_hmac('sha256', $body, 'rahasia-tes');

        $this->call('POST', '/api/payments/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertTrue($order->fresh()->isPaid());

        $this->call('POST', '/api/payments/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature], $body)->assertOk();
    }

    public function test_webhook_with_wrong_signature_is_rejected(): void
    {
        config(['services.payment.webhook_secret' => 'rahasia-tes']);
        $order = $this->order($this->customer(), $this->product(10));
        $body = json_encode(['sale_number' => $order->sale_number, 'status' => 'paid']);

        $this->call('POST', '/api/payments/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'palsu'], $body)->assertUnauthorized();
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_webhook_is_disabled_when_secret_not_configured(): void
    {
        config(['services.payment.webhook_secret' => '']);

        $this->postJson('/api/payments/webhook', ['sale_number' => 'X', 'status' => 'paid'])->assertStatus(503);
    }
}
