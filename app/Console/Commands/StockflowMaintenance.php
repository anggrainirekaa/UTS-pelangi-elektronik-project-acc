<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;

class StockflowMaintenance extends Command
{
    protected $signature = 'stockflow:maintenance';

    protected $description = 'Cek barang kedaluwarsa dan pindai anomali stok (kirim notifikasi internal)';

    public function handle(MaintenanceService $maintenance): int
    {
        $result = $maintenance->run();
        $this->info("Batch perlu perhatian: {$result['expiry']}; temuan anomali baru: {$result['anomalies']}");

        return self::SUCCESS;
    }
}
