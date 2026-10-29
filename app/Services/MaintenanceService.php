<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tugas berkala (cek kedaluwarsa & pindai anomali). Dijalankan oleh scheduler/command, atau
 * otomatis saat admin/staff membuka dashboard (maks. sekali per 6 jam) agar tetap bekerja di
 * lingkungan lokal tanpa cron.
 */
class MaintenanceService
{
    public function __construct(protected ExpiryService $expiry, protected AnomalyDetector $anomalies)
    {
    }

    public function runIfDue(): bool
    {
        if (! Cache::add('stockflow:maintenance', now()->toIso8601String(), now()->addHours(6))) {
            return false;
        }

        $this->run();

        return true;
    }

    /** @return array{expiry: int, anomalies: int} */
    public function run(): array
    {
        $result = ['expiry' => 0, 'anomalies' => 0];

        try {
            $result['expiry'] = $this->expiry->notify();
            $result['anomalies'] = $this->anomalies->scan();
        } catch (Throwable $e) {
            Log::warning('Pemeliharaan berkala gagal', ['error' => $e->getMessage()]);
        }

        return $result;
    }
}
