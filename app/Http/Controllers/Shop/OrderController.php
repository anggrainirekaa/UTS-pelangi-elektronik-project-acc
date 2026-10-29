<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\PaymentService;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pesanan milik pelanggan. Pelanggan tidak pernah melihat pesanan orang lain
 * (diperiksa oleh Gate view-sale di setiap aksi, bukan hanya di UI).
 */
class OrderController extends Controller
{
    public function __construct(protected PaymentService $payments, protected SaleService $sales)
    {
    }

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', 'in:pending,completed,cancelled']]);

        $orders = Sale::query()
            ->where('customer_id', $request->user()->id)
            ->withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('sold_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('shop.orders', compact('orders'));
    }

    public function show(Sale $sale): View
    {
        Gate::authorize('view-sale', $sale);

        $sale->load(['items.barang']);

        return view('shop.order', ['sale' => $sale, 'paymentMethods' => $this->payments->methods()]);
    }

    public function pay(Request $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('view-sale', $sale);

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys($this->payments->methods()))],
            'card_number' => ['nullable', 'string', 'max:25'],
        ]);

        try {
            $this->payments->pay($sale, $data['payment_method'], ['card_number' => $data['card_number'] ?? null]);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pesanan.show', $sale)->with('success', 'Pembayaran berhasil (simulasi).');
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('view-sale', $sale);
        abort_unless($sale->canBeCancelledBy($request->user()), 403, 'Pesanan ini tidak dapat dibatalkan.');

        try {
            $this->sales->cancel($sale, $request->user(), 'Dibatalkan oleh pelanggan');
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pesanan.show', $sale)->with('success', 'Pesanan dibatalkan.');
    }
}
