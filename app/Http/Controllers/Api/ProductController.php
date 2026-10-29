<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category_id' => ['nullable', 'integer'], 'per_page' => ['nullable', 'integer', 'between:1,50']]);

        $products = Barang::query()
            ->with('category')
            ->when($request->user()->isPelanggan(), fn ($q) => $q->active())
            ->search($request->search)
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'data' => $products->getCollection()->map(fn ($p) => $this->format($p))->values(),
            'meta' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'total' => $products->total()],
        ]);
    }

    public function show(Request $request, Barang $barang): JsonResponse
    {
        abort_if($request->user()->isPelanggan() && ! $barang->is_active, 404);

        return response()->json(['data' => $this->format($barang->load('category'))]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::orderBy('name')->get(['id', 'name', 'slug'])]);
    }

    private function format(Barang $p): array
    {
        return [
            'id' => $p->id,
            'sku' => $p->code,
            'name' => $p->name,
            'description' => $p->description,
            'category' => $p->category?->name,
            'unit' => $p->unit,
            'price' => $p->selling_price,
            'stock' => $p->stock,
            'image' => $p->image_url,
        ];
    }
}
