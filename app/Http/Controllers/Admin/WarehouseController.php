<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockOut;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::query()
            ->withSum('stocks as total_quantity', 'quantity')
            ->withCount(['stocks as product_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        return view('warehouses.form', ['warehouse' => new Warehouse]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();

            if ($data['is_default']) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }

            Warehouse::create($data);
        });

        return redirect()->route('gudang.index')->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function show(Warehouse $warehouse): View
    {
        $stocks = $warehouse->stocks()->with('barang')->where('quantity', '>', 0)->orderByDesc('quantity')->paginate(15);

        return view('warehouses.show', compact('warehouse', 'stocks'));
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.form', compact('warehouse'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        DB::transaction(function () use ($request, $warehouse) {
            $data = $request->validated();

            if ($data['is_default']) {
                Warehouse::where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            } elseif ($warehouse->is_default) {
                $data['is_default'] = true; // harus selalu ada satu gudang default
            }

            $warehouse->update($data);
        });

        return redirect()->route('gudang.index')->with('success', 'Gudang berhasil diperbarui.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->is_default) {
            return back()->with('error', 'Gudang default tidak dapat dihapus.');
        }

        $inUse = $warehouse->stocks()->where('quantity', '>', 0)->exists()
            || Purchase::where('warehouse_id', $warehouse->id)->exists()
            || Sale::where('warehouse_id', $warehouse->id)->exists()
            || StockOut::where('warehouse_id', $warehouse->id)->exists()
            || StockTransfer::where('from_warehouse_id', $warehouse->id)->orWhere('to_warehouse_id', $warehouse->id)->exists();

        if ($inUse) {
            return back()->with('error', 'Gudang tidak dapat dihapus karena masih memiliki stok atau riwayat transaksi.');
        }

        $warehouse->delete();

        return redirect()->route('gudang.index')->with('success', 'Gudang berhasil dihapus.');
    }
}
