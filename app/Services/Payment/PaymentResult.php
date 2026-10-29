<?php

namespace App\Services\Payment;

final class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $reference = null,
        public readonly string $message = '',
    ) {
    }
}
