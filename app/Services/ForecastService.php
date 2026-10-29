<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\SaleItem;
use Illuminate\Support\Collection;

/**
 * Prediksi kebutuhan stok memakai statistik sederhana (regresi linear atas penjualan mingguan).
 * BUKAN machine learning/AI: sengaja dibuat transparan agar bisa dijelaskan dan diuji.
 *
 * Langkah per produk:
 *  1. Kelompokkan penjualan selesai dalam N hari terakhir menjadi total per minggu.
 *  2. Cocokkan garis y = a + b·x (least squares) pada deret mingguan.
 *  3. Prediksi total permintaan untuk minggu-minggu ke depan (nilai tidak boleh negatif).
 *  4. Hitung sisa hari stok dan saran jumlah pembelian ulang.
 */
class ForecastService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forecast(int $historyDays = 84, int $horizonDays = 28): Collection
    {
        $weeks = max(2, (int) ceil($historyDays / 7));
        $horizonWeeks = max(1, (int) ceil($horizonDays / 7));
        $start = now()->startOfDay()->subDays($weeks * 7 - 1);

        $weekly = []; // [barang_id => [0..weeks-1 => qty]]

        SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '>=', $start)
            ->get(['sale_items.barang_id', 'sale_items.quantity', 'sales.sold_at'])
            ->each(function ($row) use (&$weekly, $start, $weeks) {
                $index = min($weeks - 1, intdiv((int) $start->diffInDays(\Illuminate\Support\Carbon::parse($row->sold_at)->startOfDay(), false), 7));
                $weekly[$row->barang_id][max(0, $index)] = ($weekly[$row->barang_id][max(0, $index)] ?? 0) + $row->quantity;
            });

        return Barang::with('category')->orderBy('name')->get()->map(function (Barang $barang) use ($weekly, $weeks, $horizonWeeks, $horizonDays) {
            $series = [];
            for ($i = 0; $i < $weeks; $i++) {
                $series[] = (float) ($weekly[$barang->id][$i] ?? 0);
            }

            [$intercept, $slope] = $this->linearRegression($series);

            $predicted = 0.0;
            for ($i = $weeks; $i < $weeks + $horizonWeeks; $i++) {
                $predicted += max(0, $intercept + $slope * $i);
            }

            $predicted = (int) ceil($predicted * ($horizonDays / ($horizonWeeks * 7)));
            $dailyDemand = $predicted / max(1, $horizonDays);
            $daysLeft = $dailyDemand > 0 ? (int) floor($barang->stock / $dailyDemand) : null;

            return [
                'barang' => $barang,
                'sold_history' => (int) array_sum($series),
                'predicted_demand' => $predicted,
                'daily_demand' => round($dailyDemand, 2),
                'days_left' => $daysLeft,
                'reorder_qty' => max(0, $predicted + $barang->minimum_stock - $barang->stock),
                'trend' => $slope > 0.05 ? 'naik' : ($slope < -0.05 ? 'turun' : 'stabil'),
                'status' => match (true) {
                    $daysLeft === null => 'tidak ada permintaan',
                    $daysLeft < 7 => 'kritis',
                    $daysLeft < 14 => 'waspada',
                    default => 'aman',
                },
            ];
        });
    }

    /**
     * Least squares: kembalikan [intercept, slope] untuk titik (0,y0),(1,y1),...
     *
     * @param  array<int, float>  $y
     * @return array{0: float, 1: float}
     */
    public function linearRegression(array $y): array
    {
        $n = count($y);

        if ($n === 0) {
            return [0.0, 0.0];
        }

        $sumX = $n * ($n - 1) / 2;
        $sumY = array_sum($y);
        $sumXY = 0.0;
        $sumXX = 0.0;

        foreach ($y as $x => $value) {
            $sumXY += $x * $value;
            $sumXX += $x * $x;
        }

        $denominator = $n * $sumXX - $sumX * $sumX;

        if ($denominator == 0) {
            return [(float) ($sumY / $n), 0.0];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;

        return [($sumY - $slope * $sumX) / $n, $slope];
    }
}
