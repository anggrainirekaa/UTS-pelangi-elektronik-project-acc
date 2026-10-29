<?php

namespace App\Services\Payment;

use App\Models\Sale;

/**
 * Kontrak payment gateway. Aplikasi memakai SimulatedPaymentGateway (tanpa uang sungguhan).
 * Untuk gateway nyata (Midtrans/Xendit) cukup buat kelas baru yang mengimplementasikan
 * interface ini lalu ganti binding di AppServiceProvider.
 */
interface PaymentGateway
{
    /** Metode pembayaran yang didukung: kode => label. */
    public function methods(): array;

    public function charge(Sale $sale, string $method, array $details = []): PaymentResult;
}
