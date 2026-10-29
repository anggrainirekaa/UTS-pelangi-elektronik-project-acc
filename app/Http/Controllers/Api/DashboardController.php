<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /** Ringkasan untuk aplikasi mobile Admin/Staff (angka dari query agregat). */
    public function index(): JsonResponse
    {
        $completed = fn () => Sale::where('status', Sale::STATUS_COMPLETED);

        return response()->json(['data' => [
            'total_products' => Barang::count(),
            'low_stock_products' => Barang::lowStock()->count(),
            'sales_today' => (int) $completed()->whereDate('sold_at', today())->sum('total'),
            'sales_month' => (int) $completed()->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
            'pending_orders' => Sale::where('status', Sale::STATUS_PENDING)->count(),
        ]]);
    }
}
