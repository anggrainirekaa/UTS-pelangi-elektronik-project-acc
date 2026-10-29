<?php

use App\Services\CurrencyService;

if (! function_exists('money')) {
    /**
     * Format nominal (tersimpan dalam IDR) ke mata uang tampilan pengguna.
     * Pass $base = true untuk selalu memakai IDR (dokumen resmi seperti invoice).
     */
    function money(int|float|null $amountIdr, bool $base = false): string
    {
        $service = app(CurrencyService::class);

        return $base ? $service->formatBase($amountIdr) : $service->format($amountIdr);
    }
}
