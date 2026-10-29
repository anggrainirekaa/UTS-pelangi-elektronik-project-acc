<?php

namespace Tests\Feature;

use App\Models\WebhookEndpoint;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class WebhookDispatcherTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function endpoint(array $events = ['sale.completed'], string $url = 'https://erp.example.test/hook'): WebhookEndpoint
    {
        return WebhookEndpoint::create(['name' => 'ERP', 'url' => $url, 'secret' => 'rahasia', 'events' => $events, 'is_active' => true]);
    }

    public function test_completed_sale_sends_signed_webhook(): void
    {
        Http::fake(['erp.example.test/*' => Http::response(['ok' => true], 200)]);
        $this->endpoint();
        $a = $this->product(5);

        $this->actingAs($this->staff())->post('/penjualan', ['warehouse_id' => \App\Models\Warehouse::default()->id, 'items' => [['barang_id' => $a->id, 'quantity' => 1]]]);

        Http::assertSent(function ($request) {
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'rahasia');

            return $request->url() === 'https://erp.example.test/hook'
                && $request->header('X-StockFlow-Event')[0] === 'sale.completed'
                && $request->header('X-StockFlow-Signature')[0] === $expected;
        });
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'sale.completed', 'succeeded' => true, 'response_status' => 200]);
    }

    public function test_endpoint_not_subscribed_to_event_gets_nothing(): void
    {
        Http::fake();
        $this->endpoint(['purchase.completed']);
        $a = $this->product(5);

        $this->actingAs($this->staff())->post('/penjualan', ['warehouse_id' => \App\Models\Warehouse::default()->id, 'items' => [['barang_id' => $a->id, 'quantity' => 1]]]);

        Http::assertNothingSent();
    }

    public function test_failing_webhook_never_breaks_the_business_transaction(): void
    {
        Http::fake(['erp.example.test/*' => Http::response('error', 500)]);
        $this->endpoint();
        $a = $this->product(5);

        $this->actingAs($this->staff())->post('/penjualan', ['warehouse_id' => \App\Models\Warehouse::default()->id, 'items' => [['barang_id' => $a->id, 'quantity' => 2]]])->assertSessionHasNoErrors();

        $this->assertSame(3, $a->fresh()->stock);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'sale.completed', 'succeeded' => false, 'response_status' => 500]);
    }

    public function test_connection_error_is_logged_not_thrown(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->endpoint();
        $a = $this->product(5);

        $this->actingAs($this->staff())->post('/penjualan', ['warehouse_id' => \App\Models\Warehouse::default()->id, 'items' => [['barang_id' => $a->id, 'quantity' => 1]]])->assertSessionHasNoErrors();

        $this->assertSame(4, $a->fresh()->stock);
        $this->assertNotNull(\App\Models\WebhookDelivery::first()->error);
    }

    public function test_non_http_url_is_never_requested(): void
    {
        Http::fake();
        $endpoint = $this->endpoint(['sale.completed'], 'file:///etc/passwd');

        app(\App\Services\WebhookDispatcher::class)->deliver($endpoint, 'ping', []);

        Http::assertNothingSent();
        $this->assertNotNull(\App\Models\WebhookDelivery::first()->error);
    }

    public function test_admin_manages_webhooks_with_validation_and_role_protection(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/integrasi/webhook', ['name' => 'X', 'url' => 'ftp://x', 'events' => ['sale.completed']])->assertSessionHasErrors('url');
        $this->actingAs($admin)->post('/integrasi/webhook', ['name' => 'X', 'url' => 'https://x.test/h', 'events' => ['bukan.event']])->assertSessionHasErrors('events.0');
        $this->actingAs($admin)->post('/integrasi/webhook', ['name' => 'X', 'url' => 'https://x.test/h', 'events' => ['sale.completed']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('webhook_endpoints', 1);

        $this->actingAs($this->staff())->post('/integrasi/webhook', ['name' => 'X', 'url' => 'https://x.test/h', 'events' => ['sale.completed']])->assertForbidden();
        $this->actingAs($this->customer())->get('/integrasi')->assertForbidden();
    }
}
