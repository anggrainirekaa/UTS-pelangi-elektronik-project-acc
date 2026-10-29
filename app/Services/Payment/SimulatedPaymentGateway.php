<?php

namespace App\Services\Payment;

use App\Models\Sale;
use Illuminate\Support\Str;

/**
 * Gateway simulasi untuk demo/UTS: tidak ada uang yang berpindah.
 * Aturan simulasi: kartu dengan nomor berakhiran 0000 selalu ditolak; selain itu sukses.
 */
class SimulatedPaymentGateway implements PaymentGateway
{
    public function methods(): array
    {
        return [
            'transfer_bank' => 'Transfer Bank (simulasi)',
            'qris' => 'QRIS (simulasi)',
            'ewallet' => 'E-Wallet (simulasi)',
            'kartu' => 'Kartu Debit/Kredit (simulasi)',
        ];
    }

    public function charge(Sale $sale, string $method, array $details = []): PaymentResult
    {
        if (! array_key_exists($method, $this->methods())) {
            return new PaymentResult(false, null, 'Metode pembayaran tidak dikenal.');
        }

        if ($method === 'kartu' && str_ends_with(preg_replace('/\D/', '', (string) ($details['card_number'] ?? '')), '0000')) {
            return new PaymentResult(false, null, 'Kartu ditolak oleh bank penerbit (simulasi).');
        }

        return new PaymentResult(true, 'SIM-'.strtoupper(Str::random(10)), 'Pembayaran berhasil (simulasi).');
    }
}
