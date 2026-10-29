<?php

namespace App\Services;

use App\Models\StockBatch;
use Illuminate\Support\Collection;

/** Pemantauan kedaluwarsa: hanya batch bertanggal milik produk yang tracks_expiry. */
class ExpiryService
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    /**
     * Batch yang sudah/akan kedaluwarsa dalam jendela peringatan produk (default 30 hari).
     *
     * @return Collection<int, StockBatch>
     */
    public function attention(?int $overrideDays = null): Collection
    {
        return StockBatch::with(['barang', 'warehouse'])
            ->inStock()
            ->whereNotNull('expiry_date')
            ->whereHas('barang', fn ($q) => $q->where('tracks_expiry', true))
            ->orderBy('expiry_date')
            ->get()
            ->filter(function (StockBatch $b) use ($overrideDays) {
                $window = $overrideDays ?? $b->barang->expiry_warning_days;

                return $b->daysToExpiry() <= $window;
            })
            ->values();
    }

    public function state(StockBatch $batch): string
    {
        $days = $batch->daysToExpiry();

        return match (true) {
            $days === null => 'none',
            $days < 0 => 'expired',
            $days <= 7 => 'critical',
            default => 'warning',
        };
    }

    /** Kirim notifikasi (dedupe per batch & kondisi). Mengembalikan jumlah batch yang diperiksa. */
    public function notify(): int
    {
        $batches = $this->attention();

        foreach ($batches as $batch) {
            $state = $this->state($batch);
            $name = $batch->barang->name;
            $label = $batch->expiry_date->translatedFormat('d M Y');

            $this->notifications->toRoles(
                ['owner', 'staff'],
                'expiry',
                $state === 'expired' ? 'Barang kedaluwarsa' : 'Barang mendekati kedaluwarsa',
                $state === 'expired'
                    ? "{$name} batch {$batch->batch_number} ({$batch->quantity} {$batch->barang->unit}) kedaluwarsa pada {$label}. Keluarkan dari stok lewat penyesuaian."
                    : "{$name} batch {$batch->batch_number} ({$batch->quantity} {$batch->barang->unit}) kedaluwarsa {$label} ({$batch->daysToExpiry()} hari lagi).",
                route('kedaluwarsa.index'),
                "exp:{$batch->id}:{$state}",
                $state === 'warning' ? 'warning' : 'danger'
            );
        }

        return $batches->count();
    }
}
