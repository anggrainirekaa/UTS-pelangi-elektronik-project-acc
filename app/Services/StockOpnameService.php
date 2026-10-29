<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Barang;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rekonsiliasi stok (stock opname).
 * Alur: draft (hitung fisik) -> submitted (diajukan) -> approved/rejected oleh admin.
 * Penyesuaian stok HANYA terjadi saat admin menyetujui. Selisih memerlukan alasan.
 * Saat disetujui, selisih diterapkan relatif terhadap snapshot (counted - system_snapshot),
 * sehingga transaksi yang terjadi selama penghitungan tidak tertimpa.
 */
class StockOpnameService
{
    public function __construct(
        protected StockService $stock,
        protected JournalService $journal,
        protected NotificationService $notifications,
    ) {
    }

    /** @param  array<int, int>|null  $barangIds  null = semua barang aktif */
    public function create(User $user, int $warehouseId, ?array $barangIds = null, ?string $note = null): StockOpname
    {
        $opname = DB::transaction(function () use ($user, $warehouseId, $barangIds, $note) {
            $opname = StockOpname::create([
                'opname_number' => NumberGenerator::temporary(),
                'warehouse_id' => $warehouseId,
                'user_id' => $user->id,
                'status' => StockOpname::STATUS_DRAFT,
                'note' => $note,
            ]);
            NumberGenerator::finalize($opname, 'SO', 'opname_number');

            $products = Barang::query()->when($barangIds, fn ($q) => $q->whereIn('id', $barangIds))->orderBy('name')->get(['id']);

            if ($products->isEmpty()) {
                throw ValidationException::withMessages(['barang_ids' => 'Tidak ada barang untuk dihitung.']);
            }

            $systems = WarehouseStock::where('warehouse_id', $warehouseId)->pluck('quantity', 'barang_id');

            foreach ($products as $product) {
                $opname->items()->create(['barang_id' => $product->id, 'system_quantity' => (int) ($systems[$product->id] ?? 0)]);
            }

            return $opname;
        });

        AuditLogger::log('opname.created', "Stock opname {$opname->opname_number} dibuat", $opname);

        return $opname;
    }

    /**
     * @param  array<int, array{counted?: int|string|null, reason?: string|null}>  $counts  barang_id => hasil hitung
     */
    public function saveCounts(StockOpname $opname, array $counts): StockOpname
    {
        return DB::transaction(function () use ($opname, $counts) {
            $locked = StockOpname::lockForUpdate()->findOrFail($opname->id);

            if ($locked->status !== StockOpname::STATUS_DRAFT) {
                throw new BusinessRuleException('Hasil hitung hanya dapat diubah pada opname berstatus draft.');
            }

            foreach ($locked->items as $item) {
                if (! array_key_exists($item->barang_id, $counts)) {
                    continue;
                }

                $row = $counts[$item->barang_id];
                $counted = ($row['counted'] ?? '') === '' || ($row['counted'] ?? null) === null ? null : (int) $row['counted'];

                if ($counted !== null && $counted < 0) {
                    throw ValidationException::withMessages(['counts' => 'Hasil hitung tidak boleh negatif.']);
                }

                $item->update([
                    'counted_quantity' => $counted,
                    'difference' => $counted === null ? 0 : $counted - $item->system_quantity,
                    'reason' => $row['reason'] ?? null,
                ]);
            }

            $locked->update(['counted_at' => now()]);

            return $locked;
        });
    }

    public function submit(StockOpname $opname, User $actor): StockOpname
    {
        $opname = DB::transaction(function () use ($opname) {
            $locked = StockOpname::with('items.barang')->lockForUpdate()->findOrFail($opname->id);

            if ($locked->status !== StockOpname::STATUS_DRAFT) {
                throw new BusinessRuleException('Opname ini sudah diajukan.');
            }

            $counted = $locked->items->whereNotNull('counted_quantity');

            if ($counted->isEmpty()) {
                throw ValidationException::withMessages(['counts' => 'Isi hasil hitung minimal untuk satu barang.']);
            }

            foreach ($counted->where('difference', '!=', 0) as $item) {
                if (mb_strlen(trim((string) $item->reason)) < 3) {
                    throw ValidationException::withMessages(['counts' => "Alasan selisih wajib diisi untuk {$item->barang->name}."]);
                }
            }

            $locked->update(['status' => StockOpname::STATUS_SUBMITTED, 'counted_at' => $locked->counted_at ?? now()]);

            return $locked;
        });

        AuditLogger::log('opname.submitted', "Stock opname {$opname->opname_number} diajukan", $opname);
        $this->notifications->toRoles(['owner'], 'opname_pending', 'Opname menunggu persetujuan', "{$opname->opname_number} perlu ditinjau.", route('opname.show', $opname), "opname-pending:{$opname->id}", 'warning');

        return $opname;
    }

    /** @throws BusinessRuleException */
    public function approve(StockOpname $opname, User $admin, ?string $note = null): StockOpname
    {
        $opname = DB::transaction(function () use ($opname, $admin, $note) {
            $locked = StockOpname::with('items')->lockForUpdate()->findOrFail($opname->id);

            if ($locked->status !== StockOpname::STATUS_SUBMITTED) {
                throw new BusinessRuleException('Hanya opname yang diajukan yang dapat disetujui.');
            }

            $costDelta = 0;

            foreach ($locked->items->sortBy('barang_id')->where('difference', '!=', 0) as $item) {
                $before = $this->stock->available($item->barang_id, $locked->warehouse_id);
                $delta = $item->difference;

                if ($before + $delta < 0) {
                    throw new BusinessRuleException('Stok barang #'.$item->barang_id.' sudah berubah sejak penghitungan sehingga selisih tidak dapat diterapkan. Buat opname ulang.');
                }

                $meta = ['type' => 'opname', 'reference' => $locked, 'user_id' => $admin->id, 'note' => 'Opname '.$locked->opname_number.': '.$item->reason];

                $delta < 0
                    ? $this->stock->decrease($item->barang_id, $locked->warehouse_id, -$delta, $meta + ['allow_expired' => true])
                    : $this->stock->increase($item->barang_id, $locked->warehouse_id, $delta, $meta);

                $item->update(['stock_before' => $before, 'stock_after' => $before + $delta]);

                $costDelta += $delta * (int) Barang::withTrashed()->whereKey($item->barang_id)->value('purchase_price');
            }

            $locked->update(['status' => StockOpname::STATUS_APPROVED, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_note' => $note]);
            $this->journal->postOpnameAdjustment($locked->fresh(), $costDelta);

            return $locked;
        });

        AuditLogger::log('opname.approved', "Stock opname {$opname->opname_number} disetujui, stok disesuaikan", $opname);
        $this->notifications->resolve("opname-pending:{$opname->id}");
        $this->notifications->toUser($opname->user, 'opname_approved', 'Opname disetujui', "{$opname->opname_number} disetujui dan stok disesuaikan.", route('opname.show', $opname), "opname-ok:{$opname->id}", 'success');

        return $opname;
    }

    public function reject(StockOpname $opname, User $admin, string $note): StockOpname
    {
        $opname = DB::transaction(function () use ($opname, $admin, $note) {
            $locked = StockOpname::lockForUpdate()->findOrFail($opname->id);

            if ($locked->status !== StockOpname::STATUS_SUBMITTED) {
                throw new BusinessRuleException('Hanya opname yang diajukan yang dapat ditolak.');
            }

            $locked->update(['status' => StockOpname::STATUS_REJECTED, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_note' => $note]);

            return $locked;
        });

        AuditLogger::log('opname.rejected', "Stock opname {$opname->opname_number} ditolak: {$note}", $opname);
        $this->notifications->resolve("opname-pending:{$opname->id}");
        $this->notifications->toUser($opname->user, 'opname_rejected', 'Opname ditolak', "{$opname->opname_number}: {$note}", route('opname.show', $opname), "opname-no:{$opname->id}", 'danger');

        return $opname;
    }
}
