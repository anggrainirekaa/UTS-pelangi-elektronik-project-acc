<?php

namespace App\Http\Controllers;

use App\Exports\BarangExport;
use App\Http\Requests\StoreBarangRequest;
use App\Http\Requests\UpdateBarangRequest;
use App\Models\Barang;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\WarehouseStock;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class BarangController extends Controller
{
    public function index(Request $request): View
    {
        $v = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive,low,out'],
            'view' => ['nullable', 'in:grid,table'],
            'sort' => ['nullable', 'in:name_asc,name_desc,stock_asc,stock_desc,price_asc,price_desc'],
        ]);

        $categoryIds = null;
        if (! empty($v['category_id'])) {
            // Memilih kategori induk juga menampilkan produk sub-kategorinya.
            $categoryIds = Category::where('id', $v['category_id'])->orWhere('parent_id', $v['category_id'])->pluck('id');
        }

        $barangs = Barang::query()
            ->with(['category', 'supplier'])
            ->search($v['search'] ?? null)
            ->when($categoryIds, fn ($q, $ids) => $q->whereIn('category_id', $ids))
            ->when($v['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($v['status'] ?? null, fn ($q, $st) => match ($st) {
                'active' => $q->where('is_active', true),
                'inactive' => $q->where('is_active', false),
                'out' => $q->outOfStock(),
                default => $q->lowStock()->where('stock', '>', 0),
            })
            ->when($request->boolean('low_stock'), fn ($q) => $q->lowStock())
            ->when($v['sort'] ?? null, function ($q, $sort) {
                match ($sort) {
                    'name_desc' => $q->orderByDesc('name'),
                    'stock_asc' => $q->orderBy('stock'),
                    'stock_desc' => $q->orderByDesc('stock'),
                    'price_asc' => $q->orderBy('selling_price'),
                    'price_desc' => $q->orderByDesc('selling_price'),
                    default => $q->orderBy('name'),
                };
            }, fn ($q) => $q->orderBy('name'))
            ->paginate(($v['view'] ?? 'table') === 'grid' ? 12 : 10)
            ->withQueryString();

        return view('barangs.index', [
            'barangs' => $barangs,
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'view' => $v['view'] ?? 'table',
        ]);
    }

    /** Aksi massal (admin): aktifkan, nonaktifkan, hapus (hanya yang stoknya 0). */
    public function bulk(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'action' => ['required', 'in:activate,deactivate,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Pilih minimal satu produk.', 'ids.min' => 'Pilih minimal satu produk.']);

        $items = Barang::whereIn('id', $v['ids'])->get();

        if ($v['action'] === 'delete') {
            $deletable = $items->filter(fn ($b) => $b->stock <= 0);
            $deletable->each->delete();
            $skipped = $items->count() - $deletable->count();

            return back()->with('success', $deletable->count().' produk dihapus.'.($skipped ? " {$skipped} dilewati karena stok masih ada." : ''));
        }

        $items->each->update(['is_active' => $v['action'] === 'activate']);

        return back()->with('success', $items->count().' produk '.($v['action'] === 'activate' ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function show(Barang $barang): View
    {
        $barang->load(['category', 'supplier']);

        return view('barangs.show', [
            'barang' => $barang,
            'warehouseStocks' => WarehouseStock::with('warehouse')->where('barang_id', $barang->id)->get(),
            'batches' => $barang->batches()->with('warehouse')->inStock()->fefo()->get(),
            'priceHistory' => $barang->priceHistories()->with('user')->limit(10)->get(),
            'movements' => $barang->movements()->with('warehouse')->latest('occurred_at')->latest('id')->limit(10)->get(),
            'recentSales' => $barang->saleItems()->with('sale')->latest()->limit(5)->get(),
            'recentPurchases' => $barang->purchaseItems()->with('purchase.supplier')->latest()->limit(5)->get(),
        ]);
    }

    public function create(): View
    {
        $categories = Category::active()->orderBy('name')->get();
        $suppliers = Supplier::active()->orderBy('name')->get();

        return view('barangs.create', compact('categories', 'suppliers'));
    }

    public function store(StoreBarangRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['stock'] = 0;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('barangs', 'public');
        }

        if (empty($data['code'])) {
            unset($data['code']); // dibuat otomatis oleh model
        }

        Barang::create($data);

        return redirect()
            ->route('barang.index')
            ->with('success', 'Barang berhasil ditambahkan. Tambahkan stok lewat menu Pembelian.');
    }

    public function edit(Barang $barang): View
    {
        $categories = Category::active()->orderBy('name')->get();
        $suppliers = Supplier::active()->orderBy('name')->get();

        return view('barangs.edit', compact('barang', 'categories', 'suppliers'));
    }

    public function update(UpdateBarangRequest $request, Barang $barang): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($barang->image) {
                Storage::disk('public')->delete($barang->image);
            }
            $data['image'] = $request->file('image')->store('barangs', 'public');
        }

        $barang->update($data);

        return redirect()
            ->route('barang.index')
            ->with('success', 'Barang berhasil diperbarui.');
    }

    /**
     * Soft delete: riwayat transaksi tetap utuh. Barang yang masih memiliki stok tidak boleh dihapus.
     */
    public function destroy(Barang $barang): RedirectResponse
    {
        if ($barang->stock > 0) {
            return redirect()
                ->route('barang.index')
                ->with('error', 'Barang tidak bisa dihapus karena stoknya masih '.$barang->stock.'.');
        }

        $barang->delete();

        return redirect()
            ->route('barang.index')
            ->with('success', 'Barang berhasil dihapus (riwayat transaksi tetap tersimpan).');
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new BarangExport($request->search, $request->category_id),
            'data-barang-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        $barangs = Barang::query()
            ->with(['category', 'supplier'])
            ->search($request->search)
            ->when($request->category_id, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->get();

        $pdf = Pdf::loadView('exports.barang-pdf', compact('barangs'))->setPaper('a4', 'landscape');

        return $pdf->download('data-barang-'.now()->format('Y-m-d').'.pdf');
    }

}
