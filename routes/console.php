<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pemeliharaan berkala (kedaluwarsa + anomali). Di server: jalankan `php artisan schedule:work` atau cron schedule:run.
Illuminate\Support\Facades\Schedule::command('stockflow:maintenance')->dailyAt('06:00');
