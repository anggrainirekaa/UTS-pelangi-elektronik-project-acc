<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreStockOutRequest;
use App\Models\Barang;
use App\Models\StockOut;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Penyesuaian stok keluar (pemakaian internal, rusak/hilang). Penjualan ada di SaleController.
 */
class StockOutController extends Controller
{
    public function __construct(protected StockAdjustmentService $adjustments)
    {
    }

    public function index(Request $request): View
    {
        $stockOuts = StockOut::query()
            ->with(['barang', 'user', 'warehouse'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('barang', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->latest('date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('stock-outs.index', compact('stockOuts'));
    }

    public function create(): View
    {
        return view('stock-outs.create', [
            'barangs' => Barang::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'defaultWarehouse' => Warehouse::default(),
        ]);
    }

    public function store(StoreStockOutRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        try {
            $this->adjustments->store($data);
        } catch (InsufficientStockException $e) {
            return redirect()->back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()
            ->route('stok-keluar.index')
            ->with('success', 'Penyesuaian stok berhasil dicatat dan stok barang telah diperbarui.');
    }

    public function destroy(StockOut $stockOut): RedirectResponse
    {
        abort_unless(auth()->user()->can('cancel-transactions'), 403);

        $this->adjustments->destroy($stockOut);

        return redirect()
            ->route('stok-keluar.index')
            ->with('success', 'Penyesuaian dihapus dan stok telah dikembalikan.');
    }
}
