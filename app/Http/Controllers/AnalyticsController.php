<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/** Analitik lanjutan: nilai persediaan, komposisi kategori, performa supplier, pergerakan per gudang. */
class AnalyticsController extends Controller
{
    public function __construct(protected MetricsService $metrics)
    {
    }

    private function params(Request $request): array
    {
        $v = $request->validate([
            'preset' => ['nullable', 'in:3d,7d,30d,12m'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'granularity' => ['nullable', 'in:auto,day,week,month,year'], 'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);
        [$start, $end] = $this->metrics->range($v['from'] ?? null, $v['to'] ?? null, $v['preset'] ?? '30d');
        $g = ($v['granularity'] ?? 'auto') === 'auto' ? $this->metrics->defaultGranularity($start, $end) : $v['granularity'];

        return [$v, $start, $end, $g];
    }

    public function index(Request $request): View
    {
        [$v, $start, $end, $g] = $this->params($request);
        $wid = $v['warehouse_id'] ?? null;

        return view('analytics.index', [
            'input' => $v, 'start' => $start, 'end' => $end, 'granularity' => $g, 'granularities' => MetricsService::GRANULARITIES,
            'warehouses' => \App\Models\Warehouse::orderBy('name')->get(),
            'sales' => $this->metrics->salesSeries($start, $end, $g),
            'movement' => $this->metrics->movementSeries($start, $end, $g, $wid),
            'inventory' => $this->metrics->inventoryValueSeries($start, $end, $g),
            'top' => $this->metrics->topProducts($start, $end, 10),
            'categories' => $this->metrics->categoryComposition(),
            'suppliers' => $this->metrics->supplierPerformance($start, $end),
            'warehousePerf' => $this->metrics->warehousePerformance($start, $end),
            'warehouseChart' => $this->metrics->warehouseMovementChart($start, $end),
        ]);
    }

    public function export(Request $request)
    {
        [$v, $start, $end, $g] = $this->params($request);
        $s = $this->metrics->salesSeries($start, $end, $g);
        $m = $this->metrics->movementSeries($start, $end, $g, $v['warehouse_id'] ?? null);
        $rows = [];
        foreach ($s['labels'] as $i => $l) {
            $rows[] = [$l, $s['values'][$i], $m['in'][$i] ?? 0, $m['out'][$i] ?? 0];
        }

        return Excel::download(new ArrayExport(['Periode', 'Penjualan bersih (Rp)', 'Barang masuk (unit)', 'Barang keluar (unit)'], $rows), 'analitik-'.now()->format('Y-m-d').'.csv', \Maatwebsite\Excel\Excel::CSV);
    }
}
