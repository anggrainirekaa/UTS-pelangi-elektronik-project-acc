<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookEndpointRequest;
use App\Models\ApiToken;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pengaturan integrasi: webhook keluar ke ERP & API key ERP/mobile.
 */
class IntegrationController extends Controller
{
    public function index(): View
    {
        return view('integrations.index', [
            'endpoints' => WebhookEndpoint::withCount('deliveries')->latest()->get(),
            'deliveries' => WebhookDelivery::with('endpoint')->latest()->limit(15)->get(),
            'tokens' => ApiToken::with('user')->latest()->get(),
            'events' => WebhookEndpoint::EVENTS,
        ]);
    }

    public function storeWebhook(WebhookEndpointRequest $request): RedirectResponse
    {
        $secret = Str::random(40);

        WebhookEndpoint::create($request->validated() + ['secret' => $secret]);

        return redirect()->route('integrasi.index')
            ->with('success', 'Webhook ditambahkan.')
            ->with('new_secret', $secret);
    }

    public function updateWebhook(WebhookEndpointRequest $request, WebhookEndpoint $webhook): RedirectResponse
    {
        $webhook->update($request->validated());

        return redirect()->route('integrasi.index')->with('success', 'Webhook diperbarui.');
    }

    public function destroyWebhook(WebhookEndpoint $webhook): RedirectResponse
    {
        $webhook->delete();

        return redirect()->route('integrasi.index')->with('success', 'Webhook dihapus.');
    }

    public function testWebhook(WebhookEndpoint $webhook, WebhookDispatcher $dispatcher): RedirectResponse
    {
        $dispatcher->deliver($webhook, 'ping', ['message' => 'Tes koneksi dari Pelangi Elektronik']);

        return redirect()->route('integrasi.index')->with('success', 'Ping dikirim. Lihat hasilnya pada log pengiriman.');
    }

    public function storeToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:erp,mobile'],
        ]);

        [, $plain] = ApiToken::issue($request->user(), $data['name'], $data['type']);

        return redirect()->route('integrasi.index')
            ->with('success', 'API key dibuat. Salin sekarang, key tidak akan ditampilkan lagi.')
            ->with('new_token', $plain);
    }

    public function destroyToken(ApiToken $token): RedirectResponse
    {
        $token->delete();

        return redirect()->route('integrasi.index')->with('success', 'API key dicabut.');
    }
}
