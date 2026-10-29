<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StockTransferRequest;
use App\Models\Barang;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(): View
    {
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'barang', 'user', 'approver'])
            ->latest('transferred_at')->latest('id')->paginate(10);

        return view('transfers.index', compact('transfers'));
    }

    public function create(): View
    {
        return view('transfers.create', [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'barangs' => Barang::orderBy('name')->get(['id', 'code', 'name', 'unit', 'stock']),
        ]);
    }

    public function store(StockTransferRequest $request, StockTransferService $service): RedirectResponse
    {
        try {
            $transfer = $service->request($request->user(), $request->validated());
        } catch (InsufficientStockException|BusinessRuleException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('transfer-stok.index')->with('success', $transfer->isPending()
            ? 'Permintaan transfer diajukan dan menunggu persetujuan admin.'
            : 'Transfer stok antar gudang berhasil.');
    }

    public function approve(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        Gate::authorize('approve-transfers');

        try {
            $service->approve($transfer, $request->user());
        } catch (InsufficientStockException|BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer disetujui dan stok dipindahkan.');
    }

    public function reject(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        Gate::authorize('approve-transfers');
        $v = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan penolakan wajib diisi.']);

        try {
            $service->reject($transfer, $request->user(), $v['reason']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer ditolak.');
    }

    public function cancel(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        abort_unless($request->user()->isManagement() || $transfer->user_id === $request->user()->id, 403);

        try {
            $service->cancel($transfer, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Permintaan transfer dibatalkan.');
    }
}
