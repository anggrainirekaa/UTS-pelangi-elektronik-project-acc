<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Services\MaintenanceService;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected MetricsService $metrics)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isPelanggan()) {
            return $this->customerDashboard($user);
        }

        // Pemeliharaan otomatis (kedaluwarsa & anomali) berjalan paling sering sekali per 6 jam.
        app(MaintenanceService::class)->runIfDue();

        $v = $request->validate([
            'preset' => ['nullable', 'in:3d,7d,30d,12m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        [$start, $end] = $this->metrics->range($v['from'] ?? null, $v['to'] ?? null, $v['preset'] ?? '30d');
        $custom = ! empty($v['from']) || ! empty($v['to']);

        return view('dashboard', [
            'period' => [
                'preset' => $custom ? '' : ($v['preset'] ?? '30d'),
                'from' => $custom ? $start->toDateString() : '',
                'to' => $custom ? $end->toDateString() : '',
                'label' => $start->translatedFormat('d M Y').' - '.$end->translatedFormat('d M Y'),
            ],
            'stats' => $this->metrics->dashboardStats($start, $end),
            'recent' => $this->metrics->recentTransactions(8),
            'activity' => $user->isManagement() ? $this->metrics->recentActivity(8) : collect(),
            'warehouses' => $this->metrics->warehousePerformance($start, $end),
            'lowStock' => Barang::with('category')->active()->lowStock()->orderBy('stock')->limit(5)->get(),
            'pending' => [
                'orders' => Sale::where('status', Sale::STATUS_PENDING)->count(),
                'purchases' => $user->isManagement() ? Purchase::where('status', Purchase::STATUS_PENDING)->count() : 0,
                'transfers' => $user->isManagement() ? StockTransfer::where('status', StockTransfer::STATUS_PENDING)->count() : 0,
                'opnames' => $user->isManagement() ? StockOpname::where('status', StockOpname::STATUS_SUBMITTED)->count() : 0,
            ],
        ]);
    }

    /** Data grafik utama dashboard (JSON). Hanya tiga grafik prioritas; analitik lanjutan ada di halaman Analitik. */
    public function data(Request $request): JsonResponse
    {
        $v = $request->validate([
            'preset' => ['nullable', 'in:3d,7d,30d,12m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'granularity' => ['nullable', 'in:auto,day,week,month,year'],
        ]);

        [$start, $end] = $this->metrics->range($v['from'] ?? null, $v['to'] ?? null, $v['preset'] ?? '30d');
        $g = ($v['granularity'] ?? 'auto') === 'auto' ? $this->metrics->defaultGranularity($start, $end) : $v['granularity'];

        return response()->json([
            'granularity' => $g,
            'range' => [$start->toDateString(), $end->toDateString()],
            'sales' => $this->metrics->salesSeries($start, $end, $g),
            'purchases' => $this->metrics->purchaseSeries($start, $end, $g),
            'movement' => $this->metrics->movementSeries($start, $end, $g),
            'top' => $this->metrics->topProducts($start, $end, 5)->map(fn ($t) => ['name' => $t->name, 'qty' => (int) $t->qty])->values(),
        ]);
    }

    private function customerDashboard($user): View
    {
        $orders = Sale::where('customer_id', $user->id);

        return view('dashboard-customer', [
            'orderCounts' => [
                'pending' => (clone $orders)->where('status', Sale::STATUS_PENDING)->count(),
                'completed' => (clone $orders)->where('status', Sale::STATUS_COMPLETED)->count(),
                'cancelled' => (clone $orders)->where('status', Sale::STATUS_CANCELLED)->count(),
            ],
            'totalSpent' => (int) (clone $orders)->where('status', Sale::STATUS_COMPLETED)->sum('total'),
            'recentOrders' => (clone $orders)->withCount('items')->latest('sold_at')->limit(5)->get(),
        ]);
    }
}
