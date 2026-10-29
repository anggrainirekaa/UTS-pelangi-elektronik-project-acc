<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pencarian pintar lintas data (nama, SKU, barcode, kategori, supplier, transaksi), dikelompokkan untuk dropdown header. */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim($data['q'] ?? '');
        $user = $request->user();

        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $internal = $user->isInternal();
        $groups = [];

        $products = Barang::with('category')->search($q)
            ->when(! $internal, fn ($b) => $b->active())
            // Kecocokan tepat pada barcode/SKU diutamakan.
            ->orderByRaw('CASE WHEN barcode = ? OR code = ? THEN 0 ELSE 1 END', [$q, $q])
            ->orderBy('name')->limit(6)->get();

        $groups[] = ['title' => 'Produk', 'items' => $products->map(fn (Barang $b) => [
            'title' => $b->name,
            'subtitle' => trim($b->code.($b->barcode ? ' · '.$b->barcode : '').' · '.($b->category?->name ?? 'Tanpa kategori')),
            'url' => $internal ? route('barang.show', $b) : route('katalog.show', $b),
            'badge' => $internal ? ['ok' => 'Stok '.$b->stock, 'low' => 'Menipis ('.$b->stock.')', 'out' => 'Habis'][$b->stockStatus()] : ($b->stock > 0 ? null : 'Habis'),
        ])->all()];

        if ($internal) {
            $like = '%'.addcslashes($q, '%_\\').'%';

            $groups[] = ['title' => 'Kategori', 'items' => Category::where('name', 'like', $like)->orderBy('name')->limit(3)->get()->map(fn ($c) => [
                'title' => $c->full_name, 'subtitle' => 'Lihat produk', 'url' => route('barang.index', ['category_id' => $c->id]), 'badge' => null,
            ])->all()];

            $groups[] = ['title' => 'Supplier', 'items' => Supplier::where('name', 'like', $like)->orderBy('name')->limit(3)->get()->map(fn ($s) => [
                'title' => $s->name, 'subtitle' => 'Lihat produk supplier', 'url' => route('barang.index', ['search' => $s->name]), 'badge' => null,
            ])->all()];

            $groups[] = ['title' => 'Penjualan', 'items' => Sale::visibleTo($user)->where('sale_number', 'like', $like)->latest('sold_at')->limit(3)->get()->map(fn ($s) => [
                'title' => $s->sale_number, 'subtitle' => money($s->total, base: true), 'url' => route('penjualan.show', $s), 'badge' => $s->statusLabel(),
            ])->all()];

            $groups[] = ['title' => 'Pembelian', 'items' => Purchase::visibleTo($user)->where('purchase_number', 'like', $like)->latest('purchased_at')->limit(3)->get()->map(fn ($p) => [
                'title' => $p->purchase_number, 'subtitle' => money($p->total, base: true), 'url' => route('pembelian.show', $p), 'badge' => $p->statusLabel(),
            ])->all()];
        }

        return response()->json(['groups' => array_values(array_filter($groups, fn ($g) => $g['items']))]);
    }
}
