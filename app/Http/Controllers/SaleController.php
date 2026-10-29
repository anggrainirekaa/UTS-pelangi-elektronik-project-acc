<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Barang;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\PaymentService;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Penjualan untuk Admin & Staff (kasir + proses pesanan online).
 */
class SaleController extends Controller
{
    public function __construct(protected SaleService $sales, protected PaymentService $payments)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,completed,cancelled'],
            'channel' => ['nullable', 'in:pos,online'],
            'payment_status' => ['nullable', 'in:unpaid,paid,refunded'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $sales = Sale::query()
            ->visibleTo($request->user())
            ->with(['customer', 'user'])
            ->withCount('items')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('sale_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->channel, fn ($q, $v) => $q->where('channel', $v))
            ->when($request->payment_status, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->start_date, fn ($q, $d) => $q->whereDate('sold_at', '>=', $d))
            ->when($request->end_date, fn ($q, $d) => $q->whereDate('sold_at', '<=', $d))
            ->latest('sold_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('sales.index', compact('sales'));
    }

    public function create(): View
    {
        $products = Barang::with('category')->orderBy('name')->get();
        $stocks = WarehouseStock::get()->groupBy('barang_id');

        $productData = $products->map(fn (Barang $b) => [
            'id' => $b->id,
            'code' => $b->code,
            'barcode' => $b->barcode,
            'name' => $b->name,
            'unit' => $b->unit,
            'price' => $b->selling_price,
            'stocks' => ($stocks[$b->id] ?? collect())->pluck('quantity', 'warehouse_id'),
        ]);

        return view('sales.create', [
            'productData' => $productData,
            'customers' => User::where('role', User::ROLE_PELANGGAN)->orderBy('name')->get(['id', 'name', 'email']),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'defaultWarehouse' => Warehouse::default(),
            'paymentMethods' => $this->payments->methods(),
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->sales->createPos($request->user(), $request->validated());
        } catch (InsufficientStockException $e) {
            return redirect()->back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('penjualan.show', $sale)
            ->with('success', "Penjualan {$sale->sale_number} berhasil disimpan dan stok berkurang.");
    }

    public function show(Sale $sale): View
    {
        Gate::authorize('view-sale', $sale);

        $sale->load(['items.barang', 'customer', 'user', 'warehouse', 'canceller', 'returns']);

        return view('sales.show', [
            'sale' => $sale,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'paymentMethods' => $this->payments->methods(),
        ]);
    }

    /** Proses pesanan online (pending -> selesai, stok berkurang). */
    public function complete(Request $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('view-sale', $sale);
        Gate::authorize('manage-sales');

        $request->validate(['warehouse_id' => ['nullable', 'exists:warehouses,id']]);

        try {
            $this->sales->complete($sale, $request->user(), $request->integer('warehouse_id') ?: null);
        } catch (InsufficientStockException|BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('penjualan.show', $sale)->with('success', 'Pesanan diproses dan stok berkurang.');
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('view-sale', $sale);
        abort_unless($sale->canBeCancelledBy($request->user()), 403, 'Anda tidak berhak membatalkan transaksi ini.');

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->sales->cancel($sale, $request->user(), $request->input('reason'));
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('penjualan.show', $sale)->with('success', 'Transaksi dibatalkan.');
    }

    /** Kasir menandai pembayaran diterima (tunai/transfer). */
    public function markPaid(Request $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('view-sale', $sale);
        Gate::authorize('manage-sales');

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', ...array_keys($this->payments->methods())])],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->sales->markPaid($sale, $data['payment_method'], $data['payment_reference'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran dicatat.');
    }

    public function invoice(Sale $sale)
    {
        Gate::authorize('view-sale', $sale);
        abort_if($sale->isCancelled() && ! auth()->user()->isInternal(), 404);

        $sale->load(['items.barang', 'customer', 'user', 'warehouse']);

        return Pdf::loadView('sales.invoice', compact('sale'))
            ->setPaper('a4')
            ->download('invoice-'.$sale->sale_number.'.pdf');
    }

    public function returnForm(Sale $sale): View|RedirectResponse
    {
        Gate::authorize('view-sale', $sale);
        Gate::authorize('process-returns');

        if (! $sale->isCompleted()) {
            return redirect()->route('penjualan.show', $sale)->with('error', 'Retur hanya untuk penjualan yang sudah selesai.');
        }

        $sale->load('items.barang');
        $returned = \Illuminate\Support\Facades\Illuminate\Support\Facades\DB::table('sale_return_items')->whereIn('sale_item_id', $sale->items->pluck('id'))
            ->selectRaw('sale_item_id, SUM(quantity) q')->groupBy('sale_item_id')->pluck('q', 'sale_item_id');

        return view('sales.return', ['sale' => $sale, 'returned' => $returned, 'windowDays' => (int) config('stockflow.return_window_days')]);
    }

    public function returnStore(Request $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('view-sale', $sale);
        Gate::authorize('process-returns');

        $v = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'request_key' => ['nullable', 'string', 'max:64'],
            'items' => ['required', 'array'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['reason.required' => 'Alasan retur wajib diisi.']);

        try {
            $return = $this->sales->returnItems($sale, $request->user(), $v);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('penjualan.show', $sale)->with('success', "Retur {$return->return_number} dicatat, stok dikembalikan.");
    }

    public function receipt(Sale $sale): View
    {
        Gate::authorize('view-sale', $sale);
        abort_if($sale->isCancelled(), 404);

        return view('sales.receipt', ['sale' => $sale->load(['items.barang', 'customer', 'user', 'warehouse'])]);
    }
}
