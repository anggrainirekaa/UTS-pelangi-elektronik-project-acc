<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Barang;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Semua angka dashboard, grafik, dan analitik dihitung dari database (tidak ada angka statis).
 * Bila data pembanding tidak ada, perubahan dikembalikan null dan UI menampilkan "Belum cukup data".
 */
class MetricsService
{
    public const GRANULARITIES = ['day' => 'Harian', 'week' => 'Mingguan', 'month' => 'Bulanan', 'year' => 'Tahunan'];

    /** Pergerakan yang dihitung sebagai barang masuk/keluar (transfer antar gudang dikecualikan). */
    private const INBOUND = ['purchase', 'sale_cancel', 'sale_return', 'manual', 'opname', 'adjustment'];

    /** @return array{0: Carbon, 1: Carbon} */
    public function range(?string $from, ?string $to, ?string $preset = null): array
    {
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $start = match (true) {
            (bool) $from => Carbon::parse($from)->startOfDay(),
            $preset === '3d' => $end->copy()->subDays(2)->startOfDay(),
            $preset === '7d' => $end->copy()->subDays(6)->startOfDay(),
            $preset === '90d' => $end->copy()->subDays(89)->startOfDay(),
            $preset === '12m' => $end->copy()->subMonths(12)->addDay()->startOfDay(),
            default => $end->copy()->subDays(29)->startOfDay(),
        };

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    public function defaultGranularity(Carbon $start, Carbon $end): string
    {
        $days = $start->diffInDays($end) + 1;

        return match (true) {
            $days <= 62 => 'day',
            $days <= 190 => 'week',
            $days <= 800 => 'month',
            default => 'year',
        };
    }

    // ------------------------------------------------------------------ Kartu statistik

    /** @return array<string, mixed> */
    public function dashboardStats(?Carbon $start = null, ?Carbon $end = null): array
    {
        $end ??= now()->endOfDay();
        $start ??= now()->subDays(29)->startOfDay();
        $days = max(1, $start->diffInDays($end) + 1);
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $start->copy()->subDays($days);

        $sales = $this->netSales($start, $end);
        $prevSales = $this->netSales($prevStart, $prevEnd);
        $purchases = $this->purchaseValue($start, $end);
        $prevPurchases = $this->purchaseValue($prevStart, $prevEnd);
        $txn = $this->saleCount($start, $end);
        $prevTxn = $this->saleCount($prevStart, $prevEnd);
        $poCount = Purchase::whereBetween('purchased_at', [$start, $end])->whereNotIn('status', [Purchase::STATUS_DRAFT, Purchase::STATUS_CANCELLED])->count();

        // Perbandingan stok/nilai hanya bila kartu stok & jurnal sudah mencakup awal periode pembanding.
        $firstMovement = StockMovement::min('occurred_at');
        $ledgerCovers = $firstMovement && Carbon::parse($firstMovement)->lte($start);

        $totalStock = (int) Barang::sum('stock');
        $movedInPeriod = (int) StockMovement::where('occurred_at', '>=', $start)->sum('quantity');
        $inventoryNow = $this->inventoryBalance($end);
        $inventoryPrev = $this->inventoryBalance($prevEnd);

        $activeNow = Barang::active()->count();
        $createdInPeriod = Barang::active()->where('created_at', '>=', $start)->count();
        $top = $this->topProducts($start, $end, 1)->first();

        return [
            'days' => $days,
            'start' => $start, 'end' => $end,
            'active_products' => $this->card($activeNow, $activeNow - $createdInPeriod > 0 ? $activeNow - $createdInPeriod : null),
            'categories' => $this->card(Category::where('is_active', true)->count(), null),
            'suppliers' => $this->card(Supplier::where('is_active', true)->count(), null),
            'total_stock' => $this->card($totalStock, $ledgerCovers ? $totalStock - $movedInPeriod : null),
            'inventory_value' => $this->card($inventoryNow, $ledgerCovers && $inventoryPrev > 0 ? $inventoryPrev : null),
            'sales' => $this->card($sales, $prevSales > 0 ? $prevSales : null),
            'transactions' => $this->card($txn, $prevTxn > 0 ? $prevTxn : null),
            'average' => $this->card($txn > 0 ? intdiv($sales, $txn) : 0, null),
            'purchases' => $this->card($purchases, $prevPurchases > 0 ? $prevPurchases : null),
            'purchase_count' => $this->card($poCount, null),
            'gross_profit' => $this->card($this->grossProfit($start, $end), null),
            'top_product' => $top ? ['name' => $top->name, 'qty' => (int) $top->qty] : null,
            'low_stock' => $this->card(Barang::active()->lowStock()->where('stock', '>', 0)->count(), null),
            'out_of_stock' => $this->card(Barang::active()->outOfStock()->count(), null),
        ];
    }

    public function saleCount(Carbon $start, Carbon $end): int
    {
        return Sale::where('status', Sale::STATUS_COMPLETED)->whereBetween('sold_at', [$start, $end])->count();
    }

    /** Laba kotor = (harga jual - harga pokok saat transaksi) x qty, hanya penjualan selesai; bukan sama dengan omzet. */
    public function grossProfit(Carbon $start, Carbon $end): int
    {
        return (int) DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)->whereBetween('sales.sold_at', [$start, $end])
            ->sum(DB::raw('sale_items.subtotal - (sale_items.unit_cost * sale_items.quantity)'));
    }

    /** @return array{value: int|float, prev: int|float|null, pct: float|null, dir: string|null} */
    private function card(int|float $value, int|float|null $prev): array
    {
        if ($prev === null || $prev <= 0) {
            return ['value' => $value, 'prev' => null, 'pct' => null, 'dir' => null];
        }

        $pct = round(($value - $prev) / $prev * 100, 1);

        return ['value' => $value, 'prev' => $prev, 'pct' => abs($pct), 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat')];
    }

    public function netSales(Carbon $start, Carbon $end): int
    {
        $gross = (int) Sale::where('status', 'completed')->whereBetween('sold_at', [$start, $end])->sum('total');
        $refunds = (int) DB::table('sale_returns')->whereBetween('returned_at', [$start, $end])->sum('refund_total');

        return $gross - $refunds;
    }

    public function purchaseValue(Carbon $start, Carbon $end): int
    {
        return (int) DB::table('purchase_receipts')->join('purchases', 'purchases.id', '=', 'purchase_receipts.purchase_id')
            ->where('purchases.status', '!=', Purchase::STATUS_CANCELLED)->whereBetween('purchase_receipts.received_at', [$start, $end])
            ->sum('purchase_receipts.total');
    }

    /** Saldo akun Persediaan (1201) pada akhir tanggal tertentu; jika belum ada jurnal, nilai stok x harga beli. */
    public function inventoryBalance(Carbon $at): int
    {
        $hasJournal = DB::table('journal_lines')->exists();

        if (! $hasJournal) {
            return $at->isToday() ? (int) Barang::sum(DB::raw('stock * purchase_price')) : 0;
        }

        $row = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')->where('accounts.code', '1201')
            ->whereDate('journal_entries.entry_date', '<=', $at->toDateString())
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')->first();

        return (int) ($row->d - $row->c);
    }

    // ------------------------------------------------------------------ Deret grafik

    /** @return array{labels: array<int, string>, values: array<int, int|float>} */
    public function salesSeries(Carbon $start, Carbon $end, string $granularity): array
    {
        $daily = DB::table('sales')->where('status', 'completed')->whereBetween('sold_at', [$start, $end])
            ->selectRaw('DATE(sold_at) as d, SUM(total) as v')->groupBy('d')->pluck('v', 'd')->map(fn ($v) => (int) $v)->all();

        foreach (DB::table('sale_returns')->whereBetween('returned_at', [$start, $end])->selectRaw('DATE(returned_at) as d, SUM(refund_total) as v')->groupBy('d')->pluck('v', 'd') as $d => $v) {
            $daily[$d] = ($daily[$d] ?? 0) - (int) $v;
        }

        return $this->bucket($daily, $start, $end, $granularity);
    }

    /** Nilai pembelian diterima per periode. @return array{labels: array<int, string>, values: array<int, int|float>} */
    public function purchaseSeries(Carbon $start, Carbon $end, string $granularity): array
    {
        $daily = DB::table('purchase_receipts')->join('purchases', 'purchases.id', '=', 'purchase_receipts.purchase_id')
            ->where('purchases.status', '!=', Purchase::STATUS_CANCELLED)->whereBetween('purchase_receipts.received_at', [$start, $end])
            ->selectRaw('DATE(purchase_receipts.received_at) as d, SUM(purchase_receipts.total) as v')->groupBy('d')->pluck('v', 'd')->map(fn ($v) => (int) $v)->all();

        return $this->bucket($daily, $start, $end, $granularity);
    }

    /** @return array{labels: array<int, string>, in: array<int, int>, out: array<int, int>} */
    public function movementSeries(Carbon $start, Carbon $end, string $granularity, ?int $warehouseId = null): array
    {
        $rows = StockMovement::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotIn('type', ['opening'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId), fn ($q) => $q->whereNotIn('type', ['transfer_in', 'transfer_out']))
            ->selectRaw('DATE(occurred_at) as d, SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as i, SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END) as o')
            ->groupBy('d')->get();

        $in = $rows->pluck('i', 'd')->map(fn ($v) => (int) $v)->all();
        $out = $rows->pluck('o', 'd')->map(fn ($v) => (int) $v)->all();

        $a = $this->bucket($in, $start, $end, $granularity);
        $b = $this->bucket($out, $start, $end, $granularity);

        return ['labels' => $a['labels'], 'in' => $a['values'], 'out' => $b['values']];
    }

    /** Nilai persediaan dari saldo jurnal pada akhir tiap periode. @return array{labels: array<int, string>, values: array<int, int>} */
    public function inventoryValueSeries(Carbon $start, Carbon $end, string $granularity): array
    {
        $net = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')->where('accounts.code', '1201')
            ->whereDate('journal_entries.entry_date', '<=', $end->toDateString())
            ->selectRaw('journal_entries.entry_date as d, SUM(journal_lines.debit - journal_lines.credit) as v')->groupBy('d')->orderBy('d')->pluck('v', 'd')->map(fn ($v) => (int) $v)->all();

        $labels = [];
        $values = [];
        $running = 0;
        $dates = array_keys($net);
        $i = 0;

        foreach ($this->buckets($start, $end, $granularity) as [$label, $bucketStart, $bucketEnd]) {
            while ($i < count($dates) && $dates[$i] <= $bucketEnd->toDateString()) {
                $running += $net[$dates[$i]];
                $i++;
            }
            $labels[] = $label;
            $values[] = $running;
        }

        return compact('labels', 'values');
    }

    /** @return Collection<int, object{name: string, code: string, qty: int}> */
    public function topProducts(Carbon $start, Carbon $end, int $limit = 5): Collection
    {
        return DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->join('barangs', 'barangs.id', '=', 'sale_items.barang_id')
            ->where('sales.status', 'completed')->whereBetween('sales.sold_at', [$start, $end])
            ->groupBy('sale_items.barang_id', 'barangs.name', 'barangs.code')
            ->orderByDesc('qty')->limit($limit)
            ->get(['barangs.name', 'barangs.code', DB::raw('SUM(sale_items.quantity) as qty'), DB::raw('SUM(sale_items.subtotal) as revenue')]);
    }

    /** @return Collection<int, array{name: string, units: int, value: int, products: int}> */
    public function categoryComposition(): Collection
    {
        return Category::with(['barangs' => fn ($q) => $q->select('id', 'category_id', 'stock', 'purchase_price')])->orderBy('name')->get()
            ->map(fn (Category $c) => [
                'name' => $c->full_name,
                'units' => (int) $c->barangs->sum('stock'),
                'value' => (int) $c->barangs->sum(fn ($b) => $b->stock * $b->purchase_price),
                'products' => $c->barangs->count(),
            ])->filter(fn ($r) => $r['products'] > 0)->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function supplierPerformance(Carbon $start, Carbon $end): Collection
    {
        return Supplier::orderBy('name')->get()->map(function (Supplier $s) use ($start, $end) {
            $orders = Purchase::with('items', 'receipts')->where('supplier_id', $s->id)->whereBetween('purchased_at', [$start, $end])
                ->whereNotIn('status', [Purchase::STATUS_DRAFT, Purchase::STATUS_PENDING])->get();

            $active = $orders->where('status', '!=', Purchase::STATUS_CANCELLED);
            $ordered = (int) $active->sum(fn ($p) => $p->items->sum('quantity'));
            $received = (int) $active->sum(fn ($p) => $p->items->sum('received_quantity'));

            $leadDays = $active->where('status', Purchase::STATUS_COMPLETED)->map(function ($p) {
                $last = $p->receipts->max('received_at');
                $from = $p->approved_at ?? $p->purchased_at;

                return $last && $from ? max(0, $from->diffInDays($last)) : null;
            })->filter(fn ($d) => $d !== null);

            return [
                'supplier' => $s->name,
                'orders' => $active->count(),
                'cancelled' => $orders->where('status', Purchase::STATUS_CANCELLED)->count(),
                'value' => (int) $active->sum(fn ($p) => $p->receipts->sum('total')),
                'ordered_qty' => $ordered,
                'received_qty' => $received,
                'fill_rate' => $ordered > 0 ? round($received / $ordered * 100, 1) : null,
                'avg_lead_days' => $leadDays->isNotEmpty() ? round($leadDays->avg(), 1) : null,
                'promised_lead_days' => $s->lead_time_days,
            ];
        })->filter(fn ($r) => $r['orders'] > 0 || $r['cancelled'] > 0)->sortByDesc('value')->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function warehousePerformance(Carbon $start, Carbon $end): Collection
    {
        $moves = StockMovement::whereBetween('occurred_at', [$start, $end])->where('type', '!=', 'opening')
            ->selectRaw('warehouse_id, SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as i, SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END) as o')
            ->groupBy('warehouse_id')->get()->keyBy('warehouse_id');

        return Warehouse::orderBy('name')->get()->map(function (Warehouse $w) use ($moves) {
            $stocks = DB::table('warehouse_stocks')->join('barangs', 'barangs.id', '=', 'warehouse_stocks.barang_id')
                ->where('warehouse_stocks.warehouse_id', $w->id)->whereNull('barangs.deleted_at')->where('warehouse_stocks.quantity', '>', 0)
                ->get(['warehouse_stocks.quantity', 'barangs.purchase_price', 'barangs.minimum_stock']);

            return [
                'warehouse' => $w,
                'units' => (int) $stocks->sum('quantity'),
                'value' => (int) $stocks->sum(fn ($s) => $s->quantity * $s->purchase_price),
                'skus' => $stocks->count(),
                'low' => $stocks->filter(fn ($s) => $s->quantity <= $s->minimum_stock)->count(),
                'in' => (int) ($moves[$w->id]->i ?? 0),
                'out' => (int) ($moves[$w->id]->o ?? 0),
            ];
        });
    }

    /** Pergerakan masuk/keluar per gudang untuk grafik batang. @return array{labels: array<int,string>, in: array<int,int>, out: array<int,int>} */
    public function warehouseMovementChart(Carbon $start, Carbon $end): array
    {
        $rows = $this->warehousePerformance($start, $end);

        return ['labels' => $rows->map(fn ($r) => $r['warehouse']->name)->all(), 'in' => $rows->pluck('in')->all(), 'out' => $rows->pluck('out')->all()];
    }

    public function recentTransactions(int $limit = 8): Collection
    {
        $sales = Sale::with('customer')->where('status', '!=', Sale::STATUS_PENDING)->latest('sold_at')->limit($limit)->get()->map(fn ($s) => [
            'kind' => 'Penjualan', 'number' => $s->sale_number, 'amount' => $s->total, 'status' => $s->status, 'at' => $s->sold_at,
            'url' => route('penjualan.show', $s), 'who' => $s->customer?->name ?? 'Umum',
        ]);

        $purchases = Purchase::with('supplier')->whereNotIn('status', [Purchase::STATUS_DRAFT])->latest('purchased_at')->limit($limit)->get()->map(fn ($p) => [
            'kind' => 'Pembelian', 'number' => $p->purchase_number, 'amount' => $p->total, 'status' => $p->status, 'at' => $p->purchased_at,
            'url' => route('pembelian.show', $p), 'who' => $p->supplier?->name,
        ]);

        return $sales->concat($purchases)->sortByDesc('at')->take($limit)->values();
    }

    public function recentActivity(int $limit = 8): Collection
    {
        return AuditLog::with('user')->latest('id')->limit($limit)->get();
    }

    // ------------------------------------------------------------------ Bucketing

    /**
     * @param  array<string, int|float>  $daily  tanggal Y-m-d => nilai
     * @return array{labels: array<int, string>, values: array<int, int|float>}
     */
    private function bucket(array $daily, Carbon $start, Carbon $end, string $granularity): array
    {
        $labels = [];
        $values = [];

        foreach ($this->buckets($start, $end, $granularity) as [$label, $from, $to]) {
            $sum = 0;
            foreach ($daily as $date => $value) {
                if ($date >= $from->toDateString() && $date <= $to->toDateString()) {
                    $sum += $value;
                }
            }
            $labels[] = $label;
            $values[] = $sum;
        }

        return compact('labels', 'values');
    }

    /** @return array<int, array{0: string, 1: Carbon, 2: Carbon}> */
    private function buckets(Carbon $start, Carbon $end, string $granularity): array
    {
        $granularity = array_key_exists($granularity, self::GRANULARITIES) ? $granularity : 'day';
        $result = [];
        $cursor = $start->copy()->startOfDay();
        $limit = 800;

        while ($cursor->lte($end) && $limit-- > 0) {
            [$from, $to, $label] = match ($granularity) {
                'week' => [$cursor->copy()->startOfWeek(), $cursor->copy()->endOfWeek(), $cursor->copy()->startOfWeek()->translatedFormat('d M')],
                'month' => [$cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth(), $cursor->copy()->translatedFormat('M Y')],
                'year' => [$cursor->copy()->startOfYear(), $cursor->copy()->endOfYear(), $cursor->format('Y')],
                default => [$cursor->copy()->startOfDay(), $cursor->copy()->endOfDay(), $cursor->translatedFormat('d M')],
            };

            // Potong ke rentang yang diminta supaya periode parsial tidak menghitung data di luar filter.
            $result[] = [$label, $from->max($start), $to->min($end)];
            $cursor = $to->copy()->addDay()->startOfDay();
        }

        return $result;
    }
}
