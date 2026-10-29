<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Sale;
use App\Services\Payment\PaymentGateway;

class PaymentService
{
    public function __construct(protected PaymentGateway $gateway, protected SaleService $sales)
    {
    }

    public function methods(): array
    {
        return $this->gateway->methods();
    }

    /**
     * @throws BusinessRuleException bila pembayaran ditolak atau transaksi tidak dapat dibayar
     */
    public function pay(Sale $sale, string $method, array $details = []): Sale
    {
        if ($sale->isCancelled()) {
            throw new BusinessRuleException('Transaksi yang dibatalkan tidak dapat dibayar.');
        }

        if ($sale->isPaid()) {
            throw new BusinessRuleException('Transaksi ini sudah lunas.');
        }

        $result = $this->gateway->charge($sale, $method, $details);

        if (! $result->success) {
            throw new BusinessRuleException($result->message);
        }

        return $this->sales->markPaid($sale, $method, $result->reference);
    }
}
