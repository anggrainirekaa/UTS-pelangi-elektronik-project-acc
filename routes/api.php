<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ErpController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
| REST API (prefix /api). Dipakai aplikasi mobile terpisah dan integrasi ERP.
| Autentikasi: Bearer token (lihat AuthenticateApiToken). Semua aturan role tetap
| ditegakkan di server lewat Gate yang sama dengan aplikasi web.
*/

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['api.token:mobile', 'throttle:120,1'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{barang}', [ProductController::class, 'show']);
        Route::get('/categories', [ProductController::class, 'categories']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{sale}', [OrderController::class, 'show']);
        Route::post('/orders/{sale}/cancel', [OrderController::class, 'cancel']);

        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('internal');
    });
});

Route::prefix('erp/v1')->middleware(['api.token:erp', 'can:view-reports', 'throttle:60,1'])->group(function () {
    Route::get('/products', [ErpController::class, 'products']);
    Route::get('/sales', [ErpController::class, 'sales']);
    Route::get('/purchases', [ErpController::class, 'purchases']);
});

Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle'])->middleware('throttle:60,1');
