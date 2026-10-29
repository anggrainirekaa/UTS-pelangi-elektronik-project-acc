<?php

namespace App\Http\Controllers;

use App\Services\RestockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Rekomendasi restock berbasis aturan + pembuatan DRAFT PO (tidak pernah memesan langsung). */
class RestockController extends Controller
{
    public function index(RestockService $restock): View
    {
        $rows = $restock->recommendations();

        return view('restock.index', [
            'groups' => $restock->groupedBySupplier($rows),
            'total' => $rows->count(),
            'config' => config('stockflow.restock'),
        ]);
    }

    public function createDrafts(Request $request, RestockService $restock): RedirectResponse
    {
        $v = $request->validate([
            'barang_ids' => ['nullable', 'array'],
            'barang_ids.*' => ['integer'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $drafts = $restock->createDrafts($request->user(), $v['barang_ids'] ?? null, $v['supplier_id'] ?? null);

        return redirect()->route('pembelian.index', ['status' => 'draft'])
            ->with('success', $drafts->count().' draft PO dibuat. Ajukan lalu minta persetujuan admin sebelum memesan ke supplier.');
    }
}
