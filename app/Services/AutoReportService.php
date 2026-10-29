<?php

namespace App\Services;

use App\Models\AnomalyFlag;
use App\Models\Barang;
use App\Models\Purchase;
use App\Models\StockBatch;
use Illuminate\Support\Carbon;

/** Laporan otomatis harian/mingguan/bulanan dari data nyata. Tidak dikirim ke luar; hanya ditampilkan/diekspor. */
class AutoReportService
{
    public const PERIODS = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'];

    public function __construct(protected MetricsService $metrics)
    {
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function range(string $period): array
    {
        $end = now()->endOfDay();

        return [match ($period) {
            'weekly' => $end->copy()->subDays(6)->startOfDay(),
            'monthly' => $end->copy()->subDays(29)->startOfDay(),
            default => $end->copy()->startOfDay(),
        }, $end];
    }

    public function build(string $period): array
    {
        [$start, $end] = $this->range($period);
        $sales = $this->metrics->netSales($start, $end);
        $purchases = $this->metrics->purchaseValue($start, $end);

        return [
            'period' => $period, 'start' => $start, 'end' => $end,
            'summary' => [
                ['Penjualan bersih', $sales],
                ['Nilai pembelian diterima', $purchases],
                ['Nilai persediaan saat ini', $this->metrics->inventoryBalance($end)],
                ['Produk stok menipis', Barang::active()->lowStock()->where('stock', '>', 0)->count()],
                ['Produk stok habis', Barang::active()->outOfStock()->count()],
                ['PO menunggu persetujuan', Purchase::where('status', Purchase::STATUS_PENDING)->count()],
                ['Batch kedaluwarsa (masih ada stok)', StockBatch::inStock()->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today())->count()],
            ],
            'top' => $this->metrics->topProducts($start, $end, 5),
            'warehouses' => $this->metrics->warehousePerformance($start, $end),
            'anomalies' => AnomalyFlag::where('status', 'open')->latest('detected_at')->limit(10)->get(),
        ];
    }

    /** Baris untuk CSV/Excel. */
    public function rows(array $r): array
    {
        $rows = [['Laporan '.self::PERIODS[$r['period']], $r['start']->format('d/m/Y').' - '.$r['end']->format('d/m/Y')], [], ['Ringkasan', 'Nilai']];
        foreach ($r['summary'] as $s) {
            $rows[] = $s;
        }
        $rows[] = [];
        $rows[] = ['Produk terlaris', 'Terjual', 'Pendapatan'];
        foreach ($r['top'] as $t) {
            $rows[] = [$t->name, (int) $t->qty, (int) $t->revenue];
        }
        $rows[] = [];
        $rows[] = ['Gudang', 'Unit', 'Nilai', 'Masuk', 'Keluar'];
        foreach ($r['warehouses'] as $w) {
            $rows[] = [$w['warehouse']->name, $w['units'], $w['value'], $w['in'], $w['out']];
        }
        $rows[] = [];
        $rows[] = ['Anomali terbuka', 'Tingkat', 'Detail'];
        foreach ($r['anomalies'] as $a) {
            $rows[] = [$a->title, $a->severity, $a->detail];
        }

        return $rows;
    }
}
