<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockOut;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Riwayat gabungan: pembelian, penjualan, penyesuaian stok, dan transfer gudang.
 * Staff hanya melihat transaksi yang diizinkan (lihat scope visibleTo pada model).
 */
class TransaksiController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'type' => ['nullable', 'in:all,purchase,sale,adjustment,transfer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $user = $request->user();
        $type = $request->get('type', 'all');
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        $rows = collect();

        if (in_array($type, ['all', 'purchase'], true)) {
            Purchase::visibleTo($user)->with(['supplier', 'user'])->withCount('items')
                ->when($start, fn ($q) => $q->whereDate('purchased_at', '>=', $start))
                ->when($end, fn ($q) => $q->whereDate('purchased_at', '<=', $end))
                ->get()
                ->each(fn (Purchase $p) => $rows->push((object) [
                    'type' => 'purchase', 'date' => $p->purchased_at, 'number' => $p->purchase_number,
                    'party' => $p->supplier->name ?? '-', 'items' => $p->items_count, 'amount' => $p->total,
                    'status' => $p->status, 'user' => $p->user, 'url' => route('pembelian.show', $p),
                ]));
        }

        if (in_array($type, ['all', 'sale'], true)) {
            Sale::visibleTo($user)->with(['customer', 'user'])->withCount('items')
                ->when($start, fn ($q) => $q->whereDate('sold_at', '>=', $start))
                ->when($end, fn ($q) => $q->whereDate('sold_at', '<=', $end))
                ->get()
                ->each(fn (Sale $s) => $rows->push((object) [
                    'type' => 'sale', 'date' => $s->sold_at, 'number' => $s->sale_number,
                    'party' => $s->customer->name ?? 'Umum', 'items' => $s->items_count, 'amount' => $s->total,
                    'status' => $s->status, 'user' => $s->user, 'url' => route('penjualan.show', $s),
                ]));
        }

        if (in_array($type, ['all', 'adjustment'], true) && $user->isManagement()) {
            StockOut::with(['barang', 'user'])
                ->when($start, fn ($q) => $q->whereDate('date', '>=', $start))
                ->when($end, fn ($q) => $q->whereDate('date', '<=', $end))
                ->get()
                ->each(fn (StockOut $o) => $rows->push((object) [
                    'type' => 'adjustment', 'date' => $o->date, 'number' => 'ADJ-'.str_pad((string) $o->id, 5, '0', STR_PAD_LEFT),
                    'party' => $o->purpose_label, 'items' => 1, 'amount' => null,
                    'status' => 'completed', 'user' => $o->user, 'url' => route('stok-keluar.index'),
                ]));
        }

        if (in_array($type, ['all', 'transfer'], true) && $user->isManagement()) {
            StockTransfer::with(['fromWarehouse', 'toWarehouse', 'user'])
                ->when($start, fn ($q) => $q->whereDate('transferred_at', '>=', $start))
                ->when($end, fn ($q) => $q->whereDate('transferred_at', '<=', $end))
                ->get()
                ->each(fn (StockTransfer $t) => $rows->push((object) [
                    'type' => 'transfer', 'date' => $t->transferred_at, 'number' => $t->transfer_number,
                    'party' => ($t->fromWarehouse->name ?? '?').' → '.($t->toWarehouse->name ?? '?'), 'items' => 1, 'amount' => null,
                    'status' => 'completed', 'user' => $t->user, 'url' => route('transfer-stok.index'),
                ]));
        }

        $rows = $rows->sortByDesc(fn ($r) => $r->date?->timestamp ?? 0)->values();

        // Pagination manual karena data berasal dari beberapa tabel.
        $perPage = 15;
        $page = max(1, (int) $request->get('page', 1));
        $transactions = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('transaksi.index', compact('transactions'));
    }
}
