<?php

namespace App\Providers;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SimulatedPaymentGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ganti dengan implementasi gateway nyata (Midtrans/Xendit) bila diperlukan.
        $this->app->bind(PaymentGateway::class, SimulatedPaymentGateway::class);

        $this->app->scoped(CurrencyService::class);
    }

    /**
     * Gate otorisasi berbasis role. Dipakai di route (middleware can:...), controller, dan Blade (@can).
     * Seluruh pengecekan dilakukan di server; menyembunyikan tombol hanyalah kosmetik.
     */
    public function boot(): void
    {
        Gate::define('manage-master', fn (User $u) => $u->isManagement());           // kategori, produk (CUD), supplier
        Gate::define('manage-users', fn (User $u) => $u->isAdmin());
        Gate::define('view-products', fn (User $u) => $u->isInternal());
        Gate::define('manage-purchases', fn (User $u) => $u->isInternal());     // buat pembelian
        Gate::define('manage-sales', fn (User $u) => $u->isInternal());         // kasir & proses pesanan
        Gate::define('manage-stock', fn (User $u) => $u->isInternal());        // penyesuaian & transfer
        Gate::define('view-reports', fn (User $u) => $u->isManagement());           // laporan, akuntansi, integrasi
        Gate::define('place-orders', fn (User $u) => $u->isPelanggan());
        Gate::define('cancel-transactions', fn (User $u) => $u->isManagement());    // batalkan transaksi selesai
        Gate::define('approve-purchases', fn (User $u) => $u->isManagement());       // setujui/tolak PO
        Gate::define('approve-transfers', fn (User $u) => $u->isManagement());       // setujui/tolak transfer besar
        Gate::define('manage-opname', fn (User $u) => $u->isInternal());        // hitung & ajukan stock opname
        Gate::define('approve-opname', fn (User $u) => $u->isManagement());          // hanya admin yang menyesuaikan stok
        Gate::define('review-anomalies', fn (User $u) => $u->isManagement());
        Gate::define('view-audit', fn (User $u) => $u->isManagement());
        Gate::define('process-returns', fn (User $u) => $u->isInternal());

        Gate::define('view-sale', fn (User $u, Sale $sale) => match (true) {
            $u->isManagement() => true,
            $u->isStaff() => $sale->user_id === $u->id || $sale->channel === Sale::CHANNEL_ONLINE,
            default => $sale->customer_id === $u->id,
        });

        Gate::define('view-purchase', fn (User $u, Purchase $purchase) => $u->isManagement()
            || ($u->isStaff() && $purchase->user_id === $u->id));
    }
}
