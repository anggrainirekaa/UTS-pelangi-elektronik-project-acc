<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pusat laporan: penjualan, pembelian, stok, stok rendah, pergerakan stok, produk terlaris, ringkasan pendapatan.
 * Setiap laporan: filter, ringkasan, tabel, tombol Cetak (window.print) dan ekspor CSV yang aman dari formula.
 */
class ReportCenterController extends Controller
{
    public const TYPES = [
        'penjualan' => 'Laporan Penjualan Rinci',
        'pembelian' => 'Laporan Pembelian',
        'stok' => 'Laporan Stok Barang',
        'stok-rendah' => 'Laporan Stok Rendah',
        'pergerakan-stok' => 'Laporan Pergerakan Stok',
        'produk-terlaris' => 'Laporan Produk Terlaris',
        'pendapatan' => 'Ringkasan Pendapatan',
    ];

    private const LIMIT = 2000;

    public function index()
    {
        return view('reports.center', ['types' => self::TYPES]);
    }

    public function show(Request $request, string $type)
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return view('reports.data', $this->build($request, $type) + ['type' => $type, 'types' => self::TYPES]);
    }

    public function csv(Request $request, string $type): StreamedResponse
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $r = $this->build($request, $type);
        $name = 'laporan-'.$type.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, [$r['title']]);
            fputcsv($out, ['Periode', $r['periodLabel']]);
            fputcsv($out, []);
            fputcsv($out, array_map([$this, 'safe'], $r['columns']));
            foreach ($r['rows'] as $row) {
                fputcsv($out, array_map([$this, 'safe'], $row));
            }
            if ($r['totals']) {
                fputcsv($out, array_map([$this, 'safe'], $r['totals']));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Mencegah injeksi formula pada Excel/Sheets. */
    private function safe(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$v;
        }

        return $v;
    }

    /** @return array<string, mixed> */
    private function build(Request $request, string $type): array
    {
        $v = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'preset' => ['nullable', 'in:3d,7d,30d,12m'],
            'product' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'move_type' => ['nullable', 'in:'.implode(',', array_keys(StockMovement::TYPES))],
        ]);

        [$start, $end] = $this->range($v);
        $f = $v + ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()];

        $result = match ($type) {
            'penjualan' => $this->sales($start, $end, $v),
            'pembelian' => $this->purchases($start, $end, $v),
            'stok' => $this->stock($v, false),
            'stok-rendah' => $this->stock($v, true),
            'pergerakan-stok' => $this->movements($start, $end, $v),
            'produk-terlaris' => $this->topProducts($start, $end, $v),
            default => $this->revenue($start, $end, $v),
        };

        $needsPeriod = in_array($type, ['penjualan', 'pembelian', 'pergerakan-stok', 'produk-terlaris', 'pendapatan'], true);

        return $result + [
            'title' => self::TYPES[$type],
            'filters' => $f,
            'needsPeriod' => $needsPeriod,
            'periodLabel' => $needsPeriod ? $start->format('d/m/Y').' - '.$end->format('d/m/Y') : 'Per '.now()->format('d/m/Y H:i'),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'staff' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_STAFF])->orderBy('name')->get(['id', 'name']),
            'moveTypes' => StockMovement::TYPES,
            'truncated' => count($result['rows']) >= self::LIMIT,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(array $v): array
    {
        if (! empty($v['start_date']) || ! empty($v['end_date'])) {
            $end = ! empty($v['end_date']) ? Carbon::parse($v['end_date'])->endOfDay() : now()->endOfDay();
            $start = ! empty($v['start_date']) ? Carbon::parse($v['start_date'])->startOfDay() : $end->copy()->startOfMonth();

            return [$start, $end];
        }

        $end = now()->endOfDay();
        $start = match ($v['preset'] ?? '30d') {
            '3d' => $end->copy()->subDays(2),
            '7d' => $end->copy()->subDays(6),
            '12m' => $end->copy()->subMonths(12)->addDay(),
            default => $end->copy()->subDays(29),
        };

        return [$start->startOfDay(), $end];
    }

    private function rp(int|float|null $n): string
    {
        return 'Rp '.number_format((int) $n, 0, ',', '.');
    }

    private function sales(Carbon $start, Carbon $end, array $v): array
    {
        $q = DB::table('sales')->join('users as u', 'u.id', '=', 'sales.user_id')->leftJoin('users as c', 'c.id', '=', 'sales.customer_id')
            ->whereBetween('sales.sold_at', [$start, $end])
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('sales.status', $s))
            ->when($v['user_id'] ?? null, fn ($q, $id) => $q->where('sales.user_id', $id))
            ->when(($v['product'] ?? null) || ($v['category_id'] ?? null), function ($q) use ($v) {
                $q->whereExists(function ($s) use ($v) {
                    $s->select(DB::raw(1))->from('sale_items')->join('barangs', 'barangs.id', '=', 'sale_items.barang_id')
                        ->whereColumn('sale_items.sale_id', 'sales.id')
                        ->when($v['product'] ?? null, fn ($s, $p) => $s->where(fn ($w) => $w->where('barangs.name', 'like', "%{$p}%")->orWhere('barangs.code', 'like', "%{$p}%")))
                        ->when($v['category_id'] ?? null, fn ($s, $c) => $s->where('barangs.category_id', $c));
                });
            });

        $rows = (clone $q)->orderByDesc('sales.sold_at')->limit(self::LIMIT)
            ->get(['sales.sale_number', 'sales.sold_at', 'sales.channel', 'sales.status', 'sales.payment_status', 'sales.total', 'u.name as staff', 'c.name as customer']);

        $sum = (clone $q)->where('sales.status', Sale::STATUS_COMPLETED)->selectRaw('COUNT(*) as n, COALESCE(SUM(sales.total),0) as t')->first();
        $labels = [Sale::STATUS_PENDING => 'Menunggu', Sale::STATUS_COMPLETED => 'Selesai', Sale::STATUS_CANCELLED => 'Dibatalkan'];

        return [
            'columns' => ['Nomor', 'Tanggal', 'Staff', 'Pelanggan', 'Kanal', 'Status', 'Pembayaran', 'Total'],
            'rows' => $rows->map(fn ($r) => [$r->sale_number, Carbon::parse($r->sold_at)->format('d/m/Y H:i'), $r->staff, $r->customer ?? '-', strtoupper($r->channel), $labels[$r->status] ?? $r->status, $r->payment_status === 'paid' ? 'Lunas' : 'Belum lunas', $this->rp($r->total)])->all(),
            'totals' => ['Total transaksi selesai', '', '', '', '', '', (string) $sum->n.' transaksi', $this->rp($sum->t)],
            'summary' => [['Transaksi selesai', (string) $sum->n], ['Omzet (selesai)', $this->rp($sum->t)], ['Rata-rata/transaksi', $this->rp($sum->n ? intdiv((int) $sum->t, (int) $sum->n) : 0)]],
            'statusOptions' => $labels,
            'fields' => ['status', 'user_id', 'category_id', 'product'],
        ];
    }

    private function purchases(Carbon $start, Carbon $end, array $v): array
    {
        $q = DB::table('purchases')->join('suppliers as s', 's.id', '=', 'purchases.supplier_id')->join('users as u', 'u.id', '=', 'purchases.user_id')
            ->whereBetween('purchases.purchased_at', [$start, $end])
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('purchases.status', $s))
            ->when($v['supplier_id'] ?? null, fn ($q, $id) => $q->where('purchases.supplier_id', $id))
            ->when($v['user_id'] ?? null, fn ($q, $id) => $q->where('purchases.user_id', $id))
            ->when(($v['product'] ?? null) || ($v['category_id'] ?? null), function ($q) use ($v) {
                $q->whereExists(function ($s) use ($v) {
                    $s->select(DB::raw(1))->from('purchase_items')->join('barangs', 'barangs.id', '=', 'purchase_items.barang_id')
                        ->whereColumn('purchase_items.purchase_id', 'purchases.id')
                        ->when($v['product'] ?? null, fn ($s, $p) => $s->where(fn ($w) => $w->where('barangs.name', 'like', "%{$p}%")->orWhere('barangs.code', 'like', "%{$p}%")))
                        ->when($v['category_id'] ?? null, fn ($s, $c) => $s->where('barangs.category_id', $c));
                });
            });

        $rows = (clone $q)->orderByDesc('purchases.purchased_at')->limit(self::LIMIT)
            ->get(['purchases.purchase_number', 'purchases.purchased_at', 'purchases.status', 'purchases.total', 's.name as supplier', 'u.name as staff']);
        $valid = (clone $q)->whereNotIn('purchases.status', [Purchase::STATUS_DRAFT, Purchase::STATUS_CANCELLED])->selectRaw('COUNT(*) as n, COALESCE(SUM(purchases.total),0) as t')->first();
        $labels = [Purchase::STATUS_DRAFT => 'Draft', Purchase::STATUS_PENDING => 'Menunggu persetujuan', Purchase::STATUS_APPROVED => 'Disetujui', Purchase::STATUS_PARTIAL => 'Diterima sebagian', Purchase::STATUS_COMPLETED => 'Selesai', Purchase::STATUS_CANCELLED => 'Dibatalkan'];

        return [
            'columns' => ['Nomor', 'Tanggal', 'Supplier', 'Dibuat oleh', 'Status', 'Total'],
            'rows' => $rows->map(fn ($r) => [$r->purchase_number, Carbon::parse($r->purchased_at)->format('d/m/Y'), $r->supplier, $r->staff, $labels[$r->status] ?? $r->status, $this->rp($r->total)])->all(),
            'totals' => ['Total pembelian (tanpa draft/batal)', '', '', '', (string) $valid->n.' pembelian', $this->rp($valid->t)],
            'summary' => [['Jumlah pembelian', (string) $valid->n], ['Nilai pembelian', $this->rp($valid->t)]],
            'statusOptions' => $labels,
            'fields' => ['status', 'supplier_id', 'user_id', 'category_id', 'product'],
        ];
    }

    private function stock(array $v, bool $lowOnly): array
    {
        $q = DB::table('barangs')->leftJoin('categories as c', 'c.id', '=', 'barangs.category_id')->leftJoin('suppliers as s', 's.id', '=', 'barangs.supplier_id')
            ->whereNull('barangs.deleted_at')
            ->when($lowOnly, fn ($q) => $q->where('barangs.is_active', true)->whereColumn('barangs.stock', '<=', 'barangs.minimum_stock'))
            ->when($v['status'] ?? null, function ($q, $s) {
                return match ($s) {
                    'active' => $q->where('barangs.is_active', true),
                    'inactive' => $q->where('barangs.is_active', false),
                    'low' => $q->whereColumn('barangs.stock', '<=', 'barangs.minimum_stock')->where('barangs.stock', '>', 0),
                    'out' => $q->where('barangs.stock', '<=', 0),
                    default => $q,
                };
            })
            ->when($v['category_id'] ?? null, fn ($q, $id) => $q->where('barangs.category_id', $id))
            ->when($v['supplier_id'] ?? null, fn ($q, $id) => $q->where('barangs.supplier_id', $id))
            ->when($v['product'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w->where('barangs.name', 'like', "%{$p}%")->orWhere('barangs.code', 'like', "%{$p}%")));

        $rows = (clone $q)->orderBy($lowOnly ? 'barangs.stock' : 'barangs.name')->limit(self::LIMIT)
            ->get(['barangs.code', 'barangs.name', 'c.name as category', 's.name as supplier', 'barangs.unit', 'barangs.stock', 'barangs.minimum_stock', 'barangs.purchase_price', 'barangs.is_active']);

        $value = $rows->sum(fn ($r) => $r->stock * $r->purchase_price);

        return [
            'columns' => ['SKU', 'Nama', 'Kategori', 'Supplier', 'Stok', 'Minimum', 'Kondisi', 'Nilai (harga beli)'],
            'rows' => $rows->map(fn ($r) => [$r->code, $r->name, $r->category ?? '-', $r->supplier ?? '-', $r->stock.' '.$r->unit, (string) $r->minimum_stock, $r->stock <= 0 ? 'Habis' : ($r->stock <= $r->minimum_stock ? 'Menipis' : 'Aman'), $this->rp($r->stock * $r->purchase_price)])->all(),
            'totals' => ['Total', '', '', '', (string) $rows->sum('stock').' unit', '', '', $this->rp($value)],
            'summary' => [['Jumlah produk', (string) $rows->count()], ['Total unit', number_format($rows->sum('stock'), 0, ',', '.')], ['Nilai persediaan', $this->rp($value)], ['Menipis/habis', (string) $rows->filter(fn ($r) => $r->stock <= $r->minimum_stock)->count()]],
            'statusOptions' => $lowOnly ? [] : ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'low' => 'Menipis', 'out' => 'Habis'],
            'fields' => ['status', 'category_id', 'supplier_id', 'product'],
        ];
    }

    private function movements(Carbon $start, Carbon $end, array $v): array
    {
        $q = DB::table('stock_movements as m')->join('barangs as b', 'b.id', '=', 'm.barang_id')->leftJoin('users as u', 'u.id', '=', 'm.user_id')->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->whereBetween('m.occurred_at', [$start, $end])
            ->when($v['move_type'] ?? null, fn ($q, $t) => $q->where('m.type', $t))
            ->when($v['category_id'] ?? null, fn ($q, $id) => $q->where('b.category_id', $id))
            ->when($v['user_id'] ?? null, fn ($q, $id) => $q->where('m.user_id', $id))
            ->when($v['product'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w->where('b.name', 'like', "%{$p}%")->orWhere('b.code', 'like', "%{$p}%")));

        $rows = (clone $q)->orderByDesc('m.occurred_at')->orderByDesc('m.id')->limit(self::LIMIT)
            ->get(['m.occurred_at', 'b.code', 'b.name', 'w.name as warehouse', 'm.type', 'm.quantity', 'm.balance_after', 'u.name as who']);
        $in = (clone $q)->where('m.quantity', '>', 0)->sum('m.quantity');
        $out = -1 * (clone $q)->where('m.quantity', '<', 0)->sum('m.quantity');

        return [
            'columns' => ['Waktu', 'SKU', 'Produk', 'Gudang', 'Jenis', 'Jumlah', 'Saldo', 'Oleh'],
            'rows' => $rows->map(fn ($r) => [Carbon::parse($r->occurred_at)->format('d/m/Y H:i'), $r->code, $r->name, $r->warehouse ?? '-', StockMovement::TYPES[$r->type] ?? $r->type, ($r->quantity > 0 ? '+' : '').$r->quantity, (string) $r->balance_after, $r->who ?? 'Sistem'])->all(),
            'totals' => ['Total', '', '', '', '', '+'.$in.' / -'.$out, '', ''],
            'summary' => [['Masuk', number_format($in, 0, ',', '.').' unit'], ['Keluar', number_format($out, 0, ',', '.').' unit'], ['Jumlah catatan', (string) $rows->count()]],
            'statusOptions' => [],
            'fields' => ['move_type', 'category_id', 'user_id', 'product'],
        ];
    }

    private function topProducts(Carbon $start, Carbon $end, array $v): array
    {
        $rows = DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')->join('barangs as b', 'b.id', '=', 'si.barang_id')->leftJoin('categories as c', 'c.id', '=', 'b.category_id')
            ->where('s.status', Sale::STATUS_COMPLETED)->whereBetween('s.sold_at', [$start, $end])
            ->when($v['category_id'] ?? null, fn ($q, $id) => $q->where('b.category_id', $id))
            ->when($v['user_id'] ?? null, fn ($q, $id) => $q->where('s.user_id', $id))
            ->when($v['product'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w->where('b.name', 'like', "%{$p}%")->orWhere('b.code', 'like', "%{$p}%")))
            ->groupBy('b.id', 'b.code', 'b.name', 'c.name')
            ->orderByDesc(DB::raw('SUM(si.quantity)'))->limit(50)
            ->get(['b.code', 'b.name', 'c.name as category', DB::raw('SUM(si.quantity) as qty'), DB::raw('SUM(si.subtotal) as revenue'), DB::raw('SUM(si.subtotal - si.unit_cost * si.quantity) as profit')]);

        return [
            'columns' => ['#', 'SKU', 'Produk', 'Kategori', 'Terjual (unit)', 'Omzet', 'Laba kotor'],
            'rows' => $rows->values()->map(fn ($r, $i) => [(string) ($i + 1), $r->code, $r->name, $r->category ?? '-', (string) $r->qty, $this->rp($r->revenue), $this->rp($r->profit)])->all(),
            'totals' => ['', '', 'Total', '', (string) $rows->sum('qty'), $this->rp($rows->sum('revenue')), $this->rp($rows->sum('profit'))],
            'summary' => [['Produk terjual', (string) $rows->count()], ['Unit terjual', number_format($rows->sum('qty'), 0, ',', '.')], ['Omzet', $this->rp($rows->sum('revenue'))]],
            'statusOptions' => [],
            'fields' => ['category_id', 'user_id', 'product'],
        ];
    }

    private function revenue(Carbon $start, Carbon $end, array $v): array
    {
        $days = DB::table('sales')->where('status', Sale::STATUS_COMPLETED)->whereBetween('sold_at', [$start, $end])
            ->when($v['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->selectRaw("DATE(sold_at) as d, COUNT(*) as n, SUM(total) as t, SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as paid")
            ->groupBy('d')->orderBy('d')->get();
        $profit = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', Sale::STATUS_COMPLETED)->whereBetween('sales.sold_at', [$start, $end])
            ->when($v['user_id'] ?? null, fn ($q, $id) => $q->where('sales.user_id', $id))
            ->selectRaw('DATE(sales.sold_at) as d, SUM(sale_items.subtotal - sale_items.unit_cost * sale_items.quantity) as p')->groupBy('d')->pluck('p', 'd');

        $totalN = $days->sum('n');
        $totalT = $days->sum('t');
        $totalP = $profit->sum();

        return [
            'columns' => ['Tanggal', 'Transaksi', 'Omzet', 'Sudah dibayar', 'Belum dibayar', 'Laba kotor'],
            'rows' => $days->map(fn ($r) => [Carbon::parse($r->d)->format('d/m/Y'), (string) $r->n, $this->rp($r->t), $this->rp($r->paid), $this->rp($r->t - $r->paid), $this->rp($profit[$r->d] ?? 0)])->all(),
            'totals' => ['Total', (string) $totalN, $this->rp($totalT), $this->rp($days->sum('paid')), $this->rp($totalT - $days->sum('paid')), $this->rp($totalP)],
            'summary' => [['Omzet', $this->rp($totalT)], ['Transaksi', (string) $totalN], ['Rata-rata/transaksi', $this->rp($totalN ? intdiv((int) $totalT, (int) $totalN) : 0)], ['Laba kotor (estimasi)', $this->rp($totalP)]],
            'statusOptions' => [],
            'fields' => ['user_id'],
        ];
    }
}
