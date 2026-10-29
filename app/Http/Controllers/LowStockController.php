<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use Illuminate\View\View;

class LowStockController extends Controller
{
    public function index(): View
    {
        $barangs = Barang::with(['category', 'supplier'])
            ->lowStock()
            ->orderBy('stock')
            ->paginate(15);

        return view('barangs.low-stock', compact('barangs'));
    }
}
