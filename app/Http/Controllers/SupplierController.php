<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->when($request->search, function ($query, $search) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->when(in_array($request->status, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->withCount('barangs')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('suppliers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()
            ->route('supplier.index')
            ->with('success', 'Supplier berhasil ditambahkan.');
    }

    /**
     * Detail pemasok: kontak, produk yang dipasok, dan riwayat pembelian.
     */
    public function show(Supplier $supplier): View
    {
        $supplier->loadCount(['barangs', 'purchases']);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'products' => $supplier->barangs()->with('category')->orderBy('name')->limit(50)->get(),
            'purchases' => $supplier->purchases()->with('user')->latest('purchased_at')->limit(15)->get(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()
            ->route('supplier.index')
            ->with('success', 'Supplier berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->barangs()->withTrashed()->exists() || $supplier->purchases()->exists()) {
            return redirect()
                ->route('supplier.index')
                ->with('error', 'Supplier tidak bisa dihapus karena masih memiliki barang atau riwayat pembelian.');
        }

        $supplier->delete();

        return redirect()
            ->route('supplier.index')
            ->with('success', 'Supplier berhasil dihapus.');
    }
}