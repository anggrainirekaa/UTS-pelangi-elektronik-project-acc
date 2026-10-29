<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(protected SaleService $sales)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Sale::query()
            ->visibleTo($request->user())
            ->withCount('items')
            ->latest('sold_at')->latest('id')
            ->paginate(15);

        return response()->json([
            'data' => $orders->getCollection()->map(fn ($s) => $this->format($s))->values(),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        Gate::authorize('view-sale', $sale);

        return response()->json(['data' => $this->format($sale->load('items.barang'), withItems: true)]);
    }

    /** Hanya pelanggan yang dapat membuat pesanan lewat API. */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('place-orders');

        $data = $request->validate([
            'shipping_address' => ['required', 'string', 'min:10', 'max:1000'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.barang_id' => ['required', 'integer', Rule::exists('barangs', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        try {
            $sale = $this->sales->createOrder($request->user(), $data);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($sale->load('items.barang'), withItems: true)], 201);
    }

    public function cancel(Request $request, Sale $sale): JsonResponse
    {
        Gate::authorize('view-sale', $sale);
        abort_unless($sale->canBeCancelledBy($request->user()), 403, 'Transaksi ini tidak dapat dibatalkan.');

        try {
            $sale = $this->sales->cancel($sale, $request->user(), 'Dibatalkan via API');
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($sale)]);
    }

    private function format(Sale $s, bool $withItems = false): array
    {
        $row = [
            'sale_number' => $s->sale_number,
            'status' => $s->status,
            'channel' => $s->channel,
            'payment_status' => $s->payment_status,
            'total' => $s->total,
            'sold_at' => $s->sold_at?->toIso8601String(),
        ];

        if ($withItems) {
            $row['items'] = $s->items->map(fn ($i) => [
                'sku' => $i->barang?->code,
                'name' => $i->barang?->name,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'subtotal' => $i->subtotal,
            ])->all();
        }

        return $row;
    }
}
