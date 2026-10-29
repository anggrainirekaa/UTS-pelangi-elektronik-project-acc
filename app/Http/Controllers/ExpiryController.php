<?php

namespace App\Http\Controllers;

use App\Models\StockBatch;
use App\Services\ExpiryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pemantauan batch & kedaluwarsa (FEFO). Hanya produk yang ditandai "memiliki kedaluwarsa" yang dilacak. */
class ExpiryController extends Controller
{
    public function index(Request $request, ExpiryService $expiry): View
    {
        $v = $request->validate(['window' => ['nullable', 'integer', 'in:7,14,30,60,90'], 'show' => ['nullable', 'in:attention,all']]);
        $show = $v['show'] ?? 'attention';

        $batches = $show === 'all'
            ? StockBatch::with(['barang', 'warehouse'])->inStock()->whereHas('barang', fn ($q) => $q->where('tracks_expiry', true))->fefo()->get()
            : $expiry->attention($v['window'] ?? null);

        return view('expiry.index', [
            'batches' => $batches->map(fn ($b) => ['batch' => $b, 'state' => $expiry->state($b), 'days' => $b->daysToExpiry()]),
            'show' => $show,
            'window' => $v['window'] ?? null,
            'expiredValue' => (int) $batches->filter(fn ($b) => $b->isExpired())->sum(fn ($b) => $b->quantity * $b->barang->purchase_price),
        ]);
    }
}
