<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rekomendasi restock berbasis aturan (bukan AI, bukan angka pasti):
 *   rata-rata harian = terjual (setelah retur) / jumlah hari histori yang tersedia
 *   target stok      = stok minimum + rata-rata harian x (lead time supplier + hari cadangan)
 *   rekomendasi      = target - (stok + sisa pesanan yang masih berjalan)
 * Bila histori kurang (< min hari atau < min transaksi), hasil ditandai "Belum cukup data"
 * dan target hanya memakai 2x stok minimum.
 */
class RestockService
{
    public function __construct(protected PurchaseService $purchases)
    {
    }

    /** @return Collection<int, array<string, mixed>> diurutkan dari paling mendesak */
    public function recommendations(?int $historyDays = null, ?int $coverageDays = null): Collection
    {
        $cfg = config('stockflow.restock');
        $historyDays ??= $cfg['history_days'];
        $coverageDays ??= $cfg['coverage_days'];
        $since = now()->subDays($historyDays)->startOfDay();

        $sold = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')->where('sales.sold_at', '>=', $since)
            ->groupBy('sale_items.barang_id')
            ->selectRaw('sale_items.barang_id, SUM(sale_items.quantity) as qty, COUNT(DISTINCT sales.id) as events')
            ->get()->keyBy('barang_id');

        $returned = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.returned_at', '>=', $since)
            ->groupBy('sale_return_items.barang_id')
            ->selectRaw('sale_return_items.barang_id, SUM(sale_return_items.quantity) as qty')
            ->pluck('qty', 'barang_id');

        $onOrder = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->whereIn('purchases.status', [Purchase::STATUS_DRAFT, Purchase::STATUS_PENDING, Purchase::STATUS_APPROVED, Purchase::STATUS_PARTIAL])
            ->groupBy('purchase_items.barang_id')
            ->selectRaw('purchase_items.barang_id, SUM(purchase_items.quantity - purchase_items.received_quantity) as qty')
            ->pluck('qty', 'barang_id');

        return Barang::with(['supplier', 'category'])->active()->get()->map(function (Barang $b) use ($sold, $returned, $onOrder, $historyDays, $coverageDays, $cfg) {
            $row = $sold->get($b->id);
            $netSold = max(0, (int) ($row->qty ?? 0) - (int) ($returned[$b->id] ?? 0));
            $events = (int) ($row->events ?? 0);

            // Hari histori yang benar-benar tersedia: sejak produk ada (maks. jendela).
            $basisDays = max(1, min($historyDays, (int) ceil($b->created_at->diffInDays(now(), true)) + 1));
            $avgDaily = $netSold / $basisDays;
            $lead = (int) ($b->supplier?->lead_time_days ?? 3);
            $enough = $basisDays >= $cfg['min_history_days'] && $events >= $cfg['min_sales_events'];

            $target = $enough
                ? $b->minimum_stock + (int) ceil($avgDaily * ($lead + $coverageDays))
                : $b->minimum_stock * 2;

            $incoming = (int) ($onOrder[$b->id] ?? 0);
            $recommended = max(0, $target - ($b->stock + $incoming));
            $daysLeft = $avgDaily > 0 ? (int) floor($b->stock / $avgDaily) : null;

            $reason = $enough
                ? sprintf('Rata-rata terjual %s %s/hari (%d hari histori, %d transaksi); lead time supplier %d hari + cadangan %d hari. Target stok %d = minimum %d + kebutuhan %d. Stok %d%s → disarankan beli %d.',
                    rtrim(rtrim(number_format($avgDaily, 2, ',', '.'), '0'), ','), $b->unit, $basisDays, $events, $lead, $coverageDays, $target, $b->minimum_stock, $target - $b->minimum_stock, $b->stock,
                    $incoming ? " + pesanan berjalan {$incoming}" : '', $recommended)
                : sprintf('Belum cukup data penjualan (%d hari histori, %d transaksi). Target sementara = 2 x stok minimum (%d). Stok %d%s → disarankan beli %d.',
                    $basisDays, $events, $target, $b->stock, $incoming ? " + pesanan berjalan {$incoming}" : '', $recommended);

            return [
                'barang' => $b,
                'supplier' => $b->supplier,
                'avg_daily' => round($avgDaily, 2),
                'sold' => $netSold,
                'events' => $events,
                'basis_days' => $basisDays,
                'lead_time' => $lead,
                'target' => $target,
                'incoming' => $incoming,
                'days_left' => $daysLeft,
                'recommended' => $recommended,
                'enough_data' => $enough,
                'urgency' => $b->stock <= 0 ? 'habis' : ($b->isLowStock() ? 'menipis' : ($daysLeft !== null && $daysLeft <= $lead ? 'segera' : 'normal')),
                'reason' => $reason,
            ];
        })->filter(fn ($r) => $r['recommended'] > 0)
            ->sortBy(fn ($r) => [['habis' => 0, 'menipis' => 1, 'segera' => 2, 'normal' => 3][$r['urgency']], $r['days_left'] ?? PHP_INT_MAX])
            ->values();
    }

    /** @return Collection<int|string, Collection<int, array<string, mixed>>> dikelompokkan per supplier_id ('none' = tanpa supplier) */
    public function groupedBySupplier(?Collection $recommendations = null): Collection
    {
        return ($recommendations ?? $this->recommendations())->groupBy(fn ($r) => $r['supplier']?->id ?? 'none');
    }

    /**
     * Buat DRAFT purchase order (bukan pesanan final) per supplier dari rekomendasi terpilih.
     * Draft harus diajukan dan disetujui admin sebelum barang dapat diterima.
     *
     * @param  array<int, int>|null  $barangIds  null = semua rekomendasi yang punya supplier
     * @return Collection<int, Purchase>
     */
    public function createDrafts(User $user, ?array $barangIds = null, ?int $supplierId = null): Collection
    {
        $rows = $this->recommendations()
            ->filter(fn ($r) => $r['supplier'] !== null)
            ->when($barangIds, fn ($c) => $c->filter(fn ($r) => in_array($r['barang']->id, $barangIds, true)))
            ->when($supplierId, fn ($c) => $c->filter(fn ($r) => $r['supplier']->id === $supplierId));

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['barang_ids' => 'Tidak ada rekomendasi dengan supplier yang dapat dibuatkan draft PO.']);
        }

        return $rows->groupBy(fn ($r) => $r['supplier']->id)->map(function ($group, $supplierId) use ($user) {
            return $this->purchases->createDraft($user, [
                'supplier_id' => (int) $supplierId,
                'warehouse_id' => Warehouse::default()->id,
                'note' => 'Draft otomatis dari rekomendasi restock '.now()->format('d/m/Y').'. Perlu ditinjau & disetujui.',
                'items' => $group->map(fn ($r) => [
                    'barang_id' => $r['barang']->id,
                    'quantity' => $r['recommended'],
                    'unit_cost' => $r['barang']->purchase_price,
                ])->values()->all(),
            ]);
        })->values();
    }
}
