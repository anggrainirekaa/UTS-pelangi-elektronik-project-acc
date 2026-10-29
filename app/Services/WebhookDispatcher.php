<?php

namespace App\Services;

use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim event bisnis ke sistem eksternal (mis. ERP) lewat HTTP POST bertanda tangan HMAC-SHA256.
 * Header: X-StockFlow-Event dan X-StockFlow-Signature (sha256=<hmac body dengan secret endpoint>).
 * Kegagalan pengiriman tidak pernah menggagalkan transaksi bisnis; hanya dicatat di webhook_deliveries.
 */
class WebhookDispatcher
{
    public function dispatch(string $event, array $payload): void
    {
        try {
            $endpoints = WebhookEndpoint::where('is_active', true)->get()
                ->filter(fn (WebhookEndpoint $e) => in_array($event, $e->events ?? [], true));
        } catch (Throwable $e) {
            Log::warning('Webhook: gagal membaca endpoint', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($endpoints as $endpoint) {
            $this->deliver($endpoint, $event, $payload);
        }
    }

    public function deliver(WebhookEndpoint $endpoint, string $event, array $payload): void
    {
        $body = json_encode([
            'event' => $event,
            'sent_at' => now()->toIso8601String(),
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $delivery = $endpoint->deliveries()->create([
            'event' => $event,
            'payload' => json_decode($body, true),
        ]);

        if (! preg_match('#^https?://#i', $endpoint->url)) {
            $delivery->update(['error' => 'URL harus diawali http:// atau https://']);

            return;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-StockFlow-Event' => $event,
                    'X-StockFlow-Signature' => 'sha256='.hash_hmac('sha256', $body, $endpoint->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->update([
                'response_status' => $response->status(),
                'succeeded' => $response->successful(),
            ]);
        } catch (Throwable $e) {
            $delivery->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
