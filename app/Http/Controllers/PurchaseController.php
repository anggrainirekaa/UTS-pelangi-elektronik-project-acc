<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\Barang;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchases)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,pending,approved,partially_received,completed,cancelled,rejected'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $purchases = Purchase::query()
            ->visibleTo($request->user())
            ->with(['supplier', 'user', 'warehouse'])
            ->withCount('items')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('purchase_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->start_date, fn ($q, $d) => $q->whereDate('purchased_at', '>=', $d))
            ->when($request->end_date, fn ($q, $d) => $q->whereDate('purchased_at', '<=', $d))
            ->latest('purchased_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('purchases.index', compact('purchases'));
    }

    public function create(Request $request): View
    {
        $products = Barang::orderBy('name')->get(['id', 'code', 'name', 'unit', 'purchase_price', 'stock']);

        return view('purchases.create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'defaultWarehouse' => Warehouse::default(),
            'products' => $products,
            'prefillBarangId' => $request->integer('barang_id') ?: null,
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // mode "draft": PO menunggu diajukan & disetujui; "langsung": barang diterima saat itu juga (stok bertambah).
        if (($data['mode'] ?? 'direct') === 'draft') {
            $purchase = $this->purchases->createDraft($request->user(), $data);
            $message = "Draft PO {$purchase->purchase_number} disimpan. Ajukan untuk persetujuan admin.";
        } else {
            $purchase = $this->purchases->create($request->user(), $data);
            $message = "Pembelian {$purchase->purchase_number} berhasil disimpan dan stok bertambah.";
        }

        return redirect()->route('pembelian.show', $purchase)->with('success', $message);
    }

    public function show(Purchase $purchase): View
    {
        Gate::authorize('view-purchase', $purchase);

        $purchase->load(['items.barang', 'supplier', 'user', 'warehouse', 'canceller', 'approver', 'receipts']);

        return view('purchases.show', compact('purchase'));
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        Gate::authorize('cancel-transactions');

        $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan pembatalan wajib diisi.']);

        try {
            $this->purchases->cancel($purchase, $request->user(), $request->input('reason'));
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pembelian.show', $purchase)
            ->with('success', 'Pembelian dibatalkan dan stok dikembalikan.');
    }

    public function submit(Request $request, Purchase $purchase): RedirectResponse
    {
        Gate::authorize('view-purchase', $purchase);

        try {
            $this->purchases->submit($purchase, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'PO diajukan untuk persetujuan admin.');
    }

    public function approve(Request $request, Purchase $purchase): RedirectResponse
    {
        Gate::authorize('approve-purchases');

        try {
            $this->purchases->approve($purchase, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'PO disetujui. Barang dapat diterima.');
    }

    public function reject(Request $request, Purchase $purchase): RedirectResponse
    {
        Gate::authorize('approve-purchases');
        $v = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan penolakan wajib diisi.']);

        try {
            $this->purchases->reject($purchase, $request->user(), $v['reason']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'PO ditolak.');
    }

    public function receiveForm(Purchase $purchase): View|RedirectResponse
    {
        Gate::authorize('view-purchase', $purchase);

        if (! $purchase->canReceive()) {
            return redirect()->route('pembelian.show', $purchase)->with('error', 'PO ini belum dapat menerima barang (harus disetujui dan belum selesai).');
        }

        return view('purchases.receive', ['purchase' => $purchase->load(['items.barang', 'supplier', 'warehouse'])]);
    }

    public function receive(Request $request, Purchase $purchase): RedirectResponse
    {
        Gate::authorize('view-purchase', $purchase);

        $v = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array'],
            'lines.*.purchase_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:60'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ]);

        try {
            $this->purchases->receive($purchase, $request->user(), $v['lines'], $v['note'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('pembelian.show', $purchase)->with('success', 'Penerimaan barang dicatat dan stok bertambah.');
    }

}
