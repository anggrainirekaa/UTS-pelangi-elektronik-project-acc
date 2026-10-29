<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API baca-saja untuk integrasi ERP eksternal (API key bertipe "erp", pemilik harus Admin).
 * Dukungan sinkronisasi inkremental lewat parameter ?updated_since=ISO8601.
 */
class ErpController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $request->validate(['updated_since' => ['nullable', 'date']]);

        $rows = Barang::with('category')
            ->when($request->updated_since, fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('id')
            ->paginate(100);

        return response()->json([
            'data' => $rows->getCollection()->map(fn ($p) => [
                'sku' => $p->code, 'name' => $p->name, 'category' => $p->category?->name, 'unit' => $p->unit,
                'purchase_price' => $p->purchase_price, 'selling_price' => $p->selling_price,
                'stock' => $p->stock, 'minimum_stock' => $p->minimum_stock, 'updated_at' => $p->updated_at->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    public function sales(Request $request): JsonResponse
    {
        $request->validate(['updated_since' => ['nullable', 'date'], 'status' => ['nullable', 'in:pending,completed,cancelled']]);

        $rows = Sale::with('items.barang', 'customer')
            ->when($request->updated_since, fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('id')
            ->paginate(100);

        return response()->json([
            'data' => $rows->getCollection()->map(fn ($s) => [
                'sale_number' => $s->sale_number, 'status' => $s->status, 'channel' => $s->channel,
                'customer' => $s->customer?->name, 'total' => $s->total, 'payment_status' => $s->payment_status,
                'sold_at' => $s->sold_at?->toIso8601String(),
                'items' => $s->items->map(fn ($i) => ['sku' => $i->barang?->code, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price])->all(),
            ])->values(),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    public function purchases(Request $request): JsonResponse
    {
        $request->validate(['updated_since' => ['nullable', 'date']]);

        $rows = Purchase::with('items.barang', 'supplier')
            ->when($request->updated_since, fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('id')
            ->paginate(100);

        return response()->json([
            'data' => $rows->getCollection()->map(fn ($p) => [
                'purchase_number' => $p->purchase_number, 'status' => $p->status, 'supplier' => $p->supplier?->name,
                'total' => $p->total, 'purchased_at' => $p->purchased_at?->toIso8601String(),
                'items' => $p->items->map(fn ($i) => ['sku' => $i->barang?->code, 'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost])->all(),
            ])->values(),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }
}
