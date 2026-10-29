<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Barang;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Keranjang belanja (disimpan di session: barang_id => qty) dan checkout.
 * Harga tidak pernah disimpan di keranjang; selalu dibaca dari database saat tampil/checkout.
 */
class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart = $request->session()->get('cart', []);
        $products = Barang::active()->whereIn('id', array_keys($cart))->get()->keyBy('id');

        $lines = collect($cart)->map(function ($qty, $id) use ($products) {
            $product = $products->get($id);

            return $product ? (object) [
                'product' => $product,
                'quantity' => $qty,
                'subtotal' => $qty * $product->selling_price,
            ] : null;
        })->filter()->values();

        return view('shop.cart', [
            'lines' => $lines,
            'total' => $lines->sum('subtotal'),
        ]);
    }

    public function add(Request $request, Barang $barang): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:1000']]);
        abort_unless($barang->is_active, 404);

        $cart = $request->session()->get('cart', []);
        $quantity = ($cart[$barang->id] ?? 0) + ($data['quantity'] ?? 1);

        if ($quantity > $barang->stock) {
            return back()->with('error', "Stok {$barang->name} hanya tersisa {$barang->stock}.");
        }

        $cart[$barang->id] = $quantity;
        $request->session()->put('cart', $cart);

        return back()->with('success', "{$barang->name} ditambahkan ke keranjang.");
    }

    public function update(Request $request, Barang $barang): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000']]);

        if ($data['quantity'] > $barang->stock) {
            return back()->with('error', "Stok {$barang->name} hanya tersisa {$barang->stock}.");
        }

        $cart = $request->session()->get('cart', []);

        if (isset($cart[$barang->id])) {
            $cart[$barang->id] = $data['quantity'];
            $request->session()->put('cart', $cart);
        }

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function remove(Request $request, Barang $barang): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$barang->id]);
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Produk dihapus dari keranjang.');
    }

    public function checkout(CheckoutRequest $request, SaleService $sales): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('keranjang.index')->with('error', 'Keranjang masih kosong.');
        }

        $items = collect($cart)->map(fn ($qty, $id) => ['barang_id' => $id, 'quantity' => $qty])->values()->all();

        try {
            $sale = $sales->createOrder($request->user(), [
                'items' => $items,
                'shipping_address' => $request->validated('shipping_address'),
                'note' => $request->validated('note'),
            ]);
        } catch (InsufficientStockException $e) {
            return redirect()->route('keranjang.index')->with('error', $e->getMessage());
        }

        $request->session()->forget('cart');

        return redirect()
            ->route('pesanan.show', $sale)
            ->with('success', "Pesanan {$sale->sale_number} berhasil dibuat. Silakan lakukan pembayaran.");
    }
}
