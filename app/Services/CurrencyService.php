<?php

namespace App\Services;

use App\Models\Currency;

/**
 * Tampilan multi-mata uang. Semua transaksi tersimpan dalam IDR (mata uang dasar);
 * service ini hanya mengonversi angka untuk TAMPILAN sesuai pilihan pengguna (session).
 */
class CurrencyService
{
    /** @var array<string, Currency> cache per kode agar tidak query berulang di satu request */
    private array $cache = [];

    public function current(): Currency
    {
        $code = session('currency');

        if ($code) {
            $currency = $this->cache[$code] ??= Currency::where('code', $code)->where('is_active', true)->first();

            if ($currency) {
                return $currency;
            }
        }

        return $this->cache['_base'] ??= Currency::base();
    }

    public function select(string $code): bool
    {
        $currency = Currency::where('code', $code)->where('is_active', true)->first();

        if (! $currency) {
            return false;
        }

        session(['currency' => $currency->code]);
        $this->cache[$currency->code] = $currency;

        return true;
    }

    public function convert(int|float $amountIdr, ?Currency $currency = null): float
    {
        $currency ??= $this->current();

        return $currency->is_base || $currency->rate <= 0 ? (float) $amountIdr : $amountIdr / $currency->rate;
    }

    public function format(int|float|null $amountIdr, ?Currency $currency = null): string
    {
        $currency ??= $this->current();
        $value = $this->convert((float) ($amountIdr ?? 0), $currency);

        if ($currency->is_base) {
            return $currency->symbol.' '.number_format($value, $currency->decimals, ',', '.');
        }

        return $currency->symbol.' '.number_format($value, $currency->decimals, '.', ',');
    }

    /** Untuk PDF/email/API yang tidak memakai session: selalu IDR. */
    public function formatBase(int|float|null $amountIdr): string
    {
        return $this->format($amountIdr, $this->cache['_base'] ??= Currency::base());
    }
}
