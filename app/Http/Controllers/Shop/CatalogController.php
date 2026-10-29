<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Katalog produk untuk pelanggan (hanya produk aktif).
 */
class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:name,newest,popular,price_asc,price_desc'],
        ]);

        $products = Barang::query()
            ->active()
            ->with('category')
            ->withSum(['saleItems as sold_qty' => fn ($q) => $q->whereHas('sale', fn ($s) => $s->where('status', 'completed'))], 'quantity')
            ->search($request->search)
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->sort, function ($q, $sort) {
                match ($sort) {
                    'newest' => $q->orderByDesc('id'),
                    'popular' => $q->orderByDesc('sold_qty')->orderBy('name'),
                    'price_asc' => $q->orderBy('selling_price'),
                    'price_desc' => $q->orderByDesc('selling_price'),
                    default => $q->orderBy('name'),
                };
            }, fn ($q) => $q->orderBy('name'))
            ->paginate(12)
            ->withQueryString();

        return view('shop.catalog', [
            'products' => $products,
            'categories' => Category::active()->whereNull('parent_id')->orderBy('name')->limit(12)->get(),
        ]);
    }

    public function show(Barang $barang): View
    {
        abort_unless($barang->is_active, 404);

        $sold = (int) $barang->saleItems()->whereHas('sale', fn ($q) => $q->where('status', 'completed'))->sum('quantity');

        $related = Barang::active()->where('id', '!=', $barang->id)->where('category_id', $barang->category_id)
            ->withSum(['saleItems as sold_qty' => fn ($q) => $q->whereHas('sale', fn ($s) => $s->where('status', 'completed'))], 'quantity')
            ->orderBy('name')->limit(5)->get();

        return view('shop.product', ['product' => $barang->load('category'), 'sold' => $sold, 'related' => $related]);
    }
}
