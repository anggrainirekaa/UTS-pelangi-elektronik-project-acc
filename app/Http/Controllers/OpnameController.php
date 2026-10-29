<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Barang;
use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Services\StockOpnameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Stock opname / rekonsiliasi: hitung fisik -> ajukan -> admin menyetujui (hanya admin yang mengubah stok). */
class OpnameController extends Controller
{
    public function __construct(protected StockOpnameService $opnames)
    {
    }

    public function index(): View
    {
        return view('opname.index', [
            'opnames' => StockOpname::with(['warehouse', 'user'])->withCount('items')->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-opname');

        return view('opname.create', [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'defaultWarehouse' => Warehouse::default(),
            'barangs' => Barang::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-opname');

        $v = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'barang_ids' => ['nullable', 'array'],
            'barang_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $opname = $this->opnames->create($request->user(), (int) $v['warehouse_id'], $v['barang_ids'] ?? null, $v['note'] ?? null);

        return redirect()->route('opname.show', $opname)->with('success', "Opname {$opname->opname_number} dibuat. Isi jumlah hitung fisik.");
    }

    public function show(StockOpname $opname): View
    {
        $opname->load(['items.barang', 'warehouse', 'user', 'reviewer']);

        return view('opname.show', compact('opname'));
    }

    public function saveCounts(Request $request, StockOpname $opname): RedirectResponse
    {
        Gate::authorize('manage-opname');

        $v = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*.counted' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'counts.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->opnames->saveCounts($opname, $v['counts']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hasil hitung disimpan.');
    }

    public function submit(Request $request, StockOpname $opname): RedirectResponse
    {
        Gate::authorize('manage-opname');

        try {
            $this->opnames->submit($opname, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('opname.show', $opname)->with('success', 'Opname diajukan ke admin untuk disetujui.');
    }

    public function approve(Request $request, StockOpname $opname): RedirectResponse
    {
        Gate::authorize('approve-opname');
        $v = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        try {
            $this->opnames->approve($opname, $request->user(), $v['note'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('opname.show', $opname)->with('success', 'Opname disetujui dan stok disesuaikan.');
    }

    public function reject(Request $request, StockOpname $opname): RedirectResponse
    {
        Gate::authorize('approve-opname');
        $v = $request->validate(['note' => ['required', 'string', 'max:255']], ['note.required' => 'Alasan penolakan wajib diisi.']);

        try {
            $this->opnames->reject($opname, $request->user(), $v['note']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('opname.show', $opname)->with('success', 'Opname ditolak. Stok tidak berubah.');
    }
}
