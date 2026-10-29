<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penerima notifikasi pembayaran dari payment gateway.
 * Request wajib bertanda tangan: header X-Signature = hex(HMAC-SHA256(body mentah, PAYMENT_WEBHOOK_SECRET)).
 */
class PaymentWebhookController extends Controller
{
    public function handle(Request $request, SaleService $sales): JsonResponse
    {
        $secret = (string) config('services.payment.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook pembayaran belum dikonfigurasi.'], 503);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            return response()->json(['message' => 'Signature tidak valid.'], 401);
        }

        $data = $request->validate([
            'sale_number' => ['required', 'string'],
            'status' => ['required', 'in:paid'],
            'reference' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'string', 'max:20'],
        ]);

        $sale = Sale::where('sale_number', $data['sale_number'])->first();

        if (! $sale) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        try {
            $sales->markPaid($sale, $data['method'] ?? 'transfer_bank', $data['reference'] ?? null);
        } catch (BusinessRuleException $e) {
            // Idempotent: notifikasi ganda untuk transaksi yang sudah lunas dianggap berhasil.
            if ($sale->fresh()->isPaid()) {
                return response()->json(['message' => 'Sudah lunas.']);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Pembayaran dicatat.']);
    }
}
