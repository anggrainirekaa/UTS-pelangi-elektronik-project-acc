<?php

namespace App\Services;

use App\Models\AnomalyFlag;
use App\Models\Barang;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Deteksi stok/transaksi tidak wajar berbasis aturan. HANYA menandai (anomaly_flags) untuk ditinjau
 * manusia; tidak pernah mengubah atau menghapus transaksi. Temuan berbasis kondisi (stok negatif,
 * saldo tidak cocok) ditutup otomatis saat kondisinya tidak lagi terdeteksi.
 */
class AnomalyDetector
{
    private const STATE_TYPES = ['negative_stock', 'balance_mismatch', 'batch_mismatch'];

    public function __construct(protected NotificationService $notifications)
    {
    }

    /** @return int jumlah temuan baru */
    public function scan(): int
    {
        $found = collect()
            ->merge($this->largeChanges())
            ->merge($this->repeatedTransactions())
            ->merge($this->negativeStock())
            ->merge($this->balanceMismatches())
            ->merge($this->batchMismatches());

        $new = 0;

        foreach ($found as $f) {
            $flag = AnomalyFlag::firstOrCreate(['fingerprint' => $f['fingerprint']], $f + ['detected_at' => now()]);
            $new += $flag->wasRecentlyCreated ? 1 : 0;

            // Kondisi yang muncul lagi setelah ditutup otomatis dibuka kembali.
            if (! $flag->wasRecentlyCreated && $flag->status !== 'open' && in_array($flag->type, self::STATE_TYPES, true) && $flag->reviewed_by === null) {
                $flag->update(['status' => 'open', 'reviewed_at' => null, 'review_note' => null]);
                $new++;
            }
        }

        // Tutup otomatis temuan kondisi yang sudah tidak terdeteksi.
        $active = $found->pluck('fingerprint')->all();
        AnomalyFlag::where('status', 'open')->whereIn('type', self::STATE_TYPES)->whereNotIn('fingerprint', $active)
            ->update(['status' => 'reviewed', 'reviewed_at' => now(), 'review_note' => 'Teratasi otomatis: kondisi tidak lagi terdeteksi.']);

        if ($new > 0) {
            $this->notifications->toRoles(['owner'], 'anomaly', 'Anomali stok terdeteksi', "{$new} temuan baru perlu ditinjau.", route('anomali.index'), 'anomaly:'.now()->format('YmdH'), 'danger');
        }

        return $new;
    }

    private function largeChanges(): array
    {
        $cfg = config('stockflow.anomaly');
        $since = now()->subDays($cfg['lookback_days']);

        $movements = StockMovement::with('barang:id,name,code,unit')
            ->where('type', '!=', 'opening')->where('occurred_at', '>=', $since)->get();

        $result = [];

        foreach ($movements->groupBy('barang_id') as $rows) {
            // Perubahan per transaksi (gabung baris batch dari referensi yang sama).
            $byRef = $rows->groupBy(fn ($m) => $m->reference_type.':'.$m->reference_id.':'.$m->type.':'.($m->reference_id ? '' : $m->id))
                ->map(fn ($g) => ['qty' => abs((int) $g->sum('quantity')), 'first' => $g->first()]);

            foreach ($byRef as $entry) {
                $others = $byRef->reject(fn ($e) => $e === $entry);
                $avg = $others->count() >= 4 ? $others->avg('qty') : null;
                $qty = $entry['qty'];

                if ($qty < $cfg['large_change_min_qty']) {
                    continue;
                }

                if ($avg !== null && $qty < $cfg['large_change_multiplier'] * $avg) {
                    continue;
                }

                $m = $entry['first'];
                $result[] = [
                    'fingerprint' => 'large:'.$m->id,
                    'type' => 'large_stock_change',
                    'severity' => $qty >= $cfg['large_change_min_qty'] * 3 ? 'high' : 'medium',
                    'title' => "Perubahan besar: {$m->barang->name}",
                    'detail' => "{$m->typeLabel()} sebesar {$qty} {$m->barang->unit}".($avg ? ' (rata-rata transaksi lain '.round($avg, 1).')' : '').' pada '.$m->occurred_at->format('d M Y H:i').'.',
                    'subject_type' => $m->barang->getMorphClass(),
                    'subject_id' => $m->barang_id,
                    'context' => ['movement_id' => $m->id, 'quantity' => $qty, 'type' => $m->type],
                ];
            }
        }

        return $result;
    }

    private function repeatedTransactions(): array
    {
        $window = config('stockflow.anomaly.repeat_window_minutes');
        $since = now()->subDays(config('stockflow.anomaly.lookback_days'));
        $result = [];

        $sales = Sale::with('items')->where('status', Sale::STATUS_COMPLETED)->where('sold_at', '>=', $since)->orderBy('sold_at')->get();

        foreach ($sales->groupBy(fn ($s) => $s->user_id.'|'.$s->items->sortBy('barang_id')->map(fn ($i) => $i->barang_id.'x'.$i->quantity)->implode(',')) as $group) {
            for ($i = 1; $i < $group->count(); $i++) {
                $prev = $group[$i - 1];
                $cur = $group[$i];

                if ($prev->sold_at->diffInMinutes($cur->sold_at) <= $window) {
                    $result[] = $this->repeat('sale', $cur->id, "Penjualan berulang: {$cur->sale_number}", "{$cur->sale_number} berisi barang & jumlah yang sama dengan {$prev->sale_number} dalam {$window} menit oleh pengguna yang sama. Periksa apakah ini input ganda.", $cur);
                }
            }
        }

        $purchases = Purchase::with('items')->whereNotIn('status', [Purchase::STATUS_CANCELLED, Purchase::STATUS_DRAFT])->where('purchased_at', '>=', $since)->orderBy('purchased_at')->get();

        foreach ($purchases->groupBy(fn ($p) => $p->user_id.'|'.$p->supplier_id.'|'.$p->items->sortBy('barang_id')->map(fn ($i) => $i->barang_id.'x'.$i->quantity)->implode(',')) as $group) {
            for ($i = 1; $i < $group->count(); $i++) {
                $prev = $group[$i - 1];
                $cur = $group[$i];

                if ($prev->purchased_at->diffInMinutes($cur->purchased_at) <= $window) {
                    $result[] = $this->repeat('purchase', $cur->id, "Pembelian berulang: {$cur->purchase_number}", "{$cur->purchase_number} identik dengan {$prev->purchase_number} dalam {$window} menit.", $cur);
                }
            }
        }

        return $result;
    }

    private function repeat(string $kind, int $id, string $title, string $detail, $model): array
    {
        return [
            'fingerprint' => "repeat:{$kind}:{$id}", 'type' => 'repeated_transaction', 'severity' => 'medium',
            'title' => $title, 'detail' => $detail, 'subject_type' => $model->getMorphClass(), 'subject_id' => $id,
            'context' => ['kind' => $kind],
        ];
    }

    private function negativeStock(): array
    {
        $result = [];

        foreach (Barang::withTrashed()->where('stock', '<', 0)->get(['id', 'name', 'stock']) as $b) {
            $result[] = $this->state('negative_stock', "neg:{$b->id}", 'high', "Stok negatif: {$b->name}", "Total stok tercatat {$b->stock}.", $b->getMorphClass(), $b->id);
        }

        $rows = DB::table('warehouse_stocks')->join('barangs', 'barangs.id', '=', 'warehouse_stocks.barang_id')
            ->where('warehouse_stocks.quantity', '<', 0)->get(['barangs.id', 'barangs.name', 'warehouse_stocks.warehouse_id', 'warehouse_stocks.quantity']);

        foreach ($rows as $r) {
            $result[] = $this->state('negative_stock', "neg:{$r->id}:{$r->warehouse_id}", 'high', "Stok gudang negatif: {$r->name}", "Saldo gudang #{$r->warehouse_id} tercatat {$r->quantity}.", (new Barang)->getMorphClass(), $r->id);
        }

        return $result;
    }

    private function balanceMismatches(): array
    {
        $result = [];

        // Total barang vs jumlah seluruh gudang.
        $sums = DB::table('warehouse_stocks')->selectRaw('barang_id, SUM(quantity) as total')->groupBy('barang_id');
        $rows = DB::table('barangs')->leftJoinSub($sums, 'ws', 'ws.barang_id', '=', 'barangs.id')
            ->whereRaw('barangs.stock != COALESCE(ws.total, 0)')
            ->get(['barangs.id', 'barangs.name', 'barangs.stock', DB::raw('COALESCE(ws.total, 0) as wh_total')]);

        foreach ($rows as $r) {
            $result[] = $this->state('balance_mismatch', "mismatch:total:{$r->id}", 'high', "Saldo tidak sinkron: {$r->name}", "Stok barang {$r->stock} tetapi total seluruh gudang {$r->wh_total}.", (new Barang)->getMorphClass(), $r->id);
        }

        // Saldo gudang vs jumlah kartu stok.
        $moves = DB::table('stock_movements')->selectRaw('barang_id, warehouse_id, SUM(quantity) as moved')->groupBy('barang_id', 'warehouse_id');
        $rows = DB::table('warehouse_stocks')->joinSub($moves, 'm', fn ($j) => $j->on('m.barang_id', '=', 'warehouse_stocks.barang_id')->on('m.warehouse_id', '=', 'warehouse_stocks.warehouse_id'))
            ->join('barangs', 'barangs.id', '=', 'warehouse_stocks.barang_id')
            ->whereRaw('warehouse_stocks.quantity != m.moved')
            ->get(['barangs.id', 'barangs.name', 'warehouse_stocks.warehouse_id', 'warehouse_stocks.quantity', 'm.moved']);

        foreach ($rows as $r) {
            $result[] = $this->state('balance_mismatch', "mismatch:ledger:{$r->id}:{$r->warehouse_id}", 'high', "Saldo tidak sesuai transaksi: {$r->name}", "Saldo gudang #{$r->warehouse_id} = {$r->quantity}, tetapi jumlah seluruh pergerakan stok = {$r->moved}.", (new Barang)->getMorphClass(), $r->id);
        }

        return $result;
    }

    private function batchMismatches(): array
    {
        $result = [];
        $sums = DB::table('stock_batches')->selectRaw('barang_id, warehouse_id, SUM(quantity) as batch_total')->groupBy('barang_id', 'warehouse_id');

        $rows = DB::table('warehouse_stocks')->joinSub($sums, 'b', fn ($j) => $j->on('b.barang_id', '=', 'warehouse_stocks.barang_id')->on('b.warehouse_id', '=', 'warehouse_stocks.warehouse_id'))
            ->join('barangs', 'barangs.id', '=', 'warehouse_stocks.barang_id')
            ->whereRaw('b.batch_total > warehouse_stocks.quantity')
            ->get(['barangs.id', 'barangs.name', 'warehouse_stocks.warehouse_id', 'warehouse_stocks.quantity', 'b.batch_total']);

        foreach ($rows as $r) {
            $result[] = $this->state('batch_mismatch', "batch:{$r->id}:{$r->warehouse_id}", 'high', "Batch melebihi stok: {$r->name}", "Jumlah seluruh batch {$r->batch_total} melebihi saldo gudang {$r->quantity}.", (new Barang)->getMorphClass(), $r->id);
        }

        return $result;
    }

    private function state(string $type, string $fingerprint, string $severity, string $title, string $detail, string $subjectType, int $subjectId): array
    {
        return compact('type', 'fingerprint', 'severity', 'title', 'detail') + ['subject_type' => $subjectType, 'subject_id' => $subjectId, 'context' => null];
    }
}
