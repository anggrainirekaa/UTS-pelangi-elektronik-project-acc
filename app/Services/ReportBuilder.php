<?php

namespace App\Services;

use App\Models\AnomalyFlag;
use App\Models\Barang;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Carbon;

/** Ringkasan laporan otomatis harian/mingguan/bulanan dari data nyata. Tidak mengirim apa pun ke luar. */
class ReportBuilder
{
    public const PERIODS = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'];

    public function __construct(protected MetricsService $metrics)
    {
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function range(string $period): array
    {
        return match ($period) {
            'weekly' => [now()->startOfWeek(), now()->endOfDay()],
            'monthly' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    /** @return array<string, mixed> */
    public function build(string $period): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'daily';
        [$start, $end] = $this->range($period);

        $sales = Sale::where('status', 'completed')->whereBetween('sold_at', [$start, $end]);
        $open = AnomalyFlag::where('status', 'open')->count();

        return [
            'period' => $period, 'label' => self::PERIODS[$period], 'start' => $start, 'end' => $end,
            'sales_net' => $this->metrics->netSales($start, $end),
            'sales_count' => (clone $sales)->count(),
            'purchase_value' => $this->metrics->purchaseValue($start, $end),
            'purchase_count' => Purchase::whereBetween('purchased_at', [$start, $end])->whereNotIn('status', ['draft', 'cancelled'])->count(),
            'inventory_value' => $this->metrics->inventoryBalance($end),
            'low_stock' => Barang::active()->lowStock()->where('stock', '>', 0)->count(),
            'out_of_stock' => Barang::active()->outOfStock()->count(),
            'top' => $this->metrics->topProducts($start, $end, 5),
            'open_anomalies' => $open,
            'anomalies' => AnomalyFlag::where('status', 'open')->orderByRaw("CASE severity WHEN 'high' THEN 0 ELSE 1 END")->limit(8)->get(),
        ] + ['summary' => [
            ['Penjualan bersih', $this->metrics->netSales($start, $end)],
            ['Transaksi penjualan', (clone $sales)->count()],
            ['Nilai pembelian diterima', $this->metrics->purchaseValue($start, $end)],
            ['Nilai persediaan', $this->metrics->inventoryBalance($end)],
            ['Produk stok menipis', Barang::active()->lowStock()->where('stock', '>', 0)->count()],
            ['Produk stok habis', Barang::active()->outOfStock()->count()],
            ['Anomali belum ditinjau', $open],
        ]];
    }

    /** Baris tabel ekspor. @return array{0: array<int,string>, 1: array<int, array<int, mixed>>} */
    public function rows(array $r): array
    {
        $rows = [
            ['Periode', $r['label'].' ('.$r['start']->format('d/m/Y').' - '.$r['end']->format('d/m/Y').')'],
            ['Penjualan bersih (Rp)', $r['sales_net']],
            ['Jumlah transaksi penjualan', $r['sales_count']],
            ['Nilai pembelian diterima (Rp)', $r['purchase_value']],
            ['Jumlah pembelian', $r['purchase_count']],
            ['Nilai persediaan (Rp)', $r['inventory_value']],
            ['Produk stok menipis', $r['low_stock']],
            ['Produk stok habis', $r['out_of_stock']],
            ['Anomali belum ditinjau', $r['open_anomalies']],
        ];

        foreach ($r['top'] as $i => $t) {
            $rows[] = ['Produk terlaris #'.($i + 1), $t->name.' ('.(int) $t->qty.' unit)'];
        }

        return [['Keterangan', 'Nilai'], $rows];
    }
}
