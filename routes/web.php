<?php

use App\Http\Controllers\Admin\AnomalyController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AutoReportController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\ForecastController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\ExpiryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpnameController;
use App\Http\Controllers\RestockController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LowStockController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/mata-uang/pilih', [CurrencyController::class, 'select'])->name('mata-uang.select');

    Route::get('/pencarian', [SearchController::class, 'index'])->name('pencarian');

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifikasi.index');
    Route::get('/notifikasi/{id}/buka', [NotificationController::class, 'open'])->name('notifikasi.open');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifikasi.read-all');
});

/*
| PELANGGAN: katalog, keranjang, pesanan sendiri.
*/
Route::middleware(['auth', 'role:pelanggan'])->group(function () {
    Route::get('/katalog', [CatalogController::class, 'index'])->name('katalog.index');
    Route::get('/katalog/{barang}', [CatalogController::class, 'show'])->name('katalog.show');

    Route::get('/keranjang', [CartController::class, 'index'])->name('keranjang.index');
    Route::post('/keranjang/checkout', [CartController::class, 'checkout'])->name('keranjang.checkout');
    Route::post('/keranjang/{barang}', [CartController::class, 'add'])->name('keranjang.add');
    Route::patch('/keranjang/{barang}', [CartController::class, 'update'])->name('keranjang.update');
    Route::delete('/keranjang/{barang}', [CartController::class, 'remove'])->name('keranjang.remove');

    Route::get('/pesanan-saya', [OrderController::class, 'index'])->name('pesanan.index');
    Route::get('/pesanan-saya/{sale}', [OrderController::class, 'show'])->name('pesanan.show');
    Route::post('/pesanan-saya/{sale}/bayar', [OrderController::class, 'pay'])->name('pesanan.pay');
    Route::post('/pesanan-saya/{sale}/batal', [OrderController::class, 'cancel'])->name('pesanan.cancel');
});

/*
| OWNER: data master, pengguna, gudang, laporan, akuntansi, integrasi.
*/
Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::resource('pengguna', UserController::class)->parameters(['pengguna' => 'user'])->except('show');

    Route::resource('mata-uang', CurrencyController::class)->parameters(['mata-uang' => 'currency'])->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('/integrasi', [IntegrationController::class, 'index'])->name('integrasi.index');
    Route::post('/integrasi/webhook', [IntegrationController::class, 'storeWebhook'])->name('integrasi.webhook.store');
    Route::patch('/integrasi/webhook/{webhook}', [IntegrationController::class, 'updateWebhook'])->name('integrasi.webhook.update');
    Route::delete('/integrasi/webhook/{webhook}', [IntegrationController::class, 'destroyWebhook'])->name('integrasi.webhook.destroy');
    Route::post('/integrasi/webhook/{webhook}/tes', [IntegrationController::class, 'testWebhook'])->name('integrasi.webhook.test');
    Route::post('/integrasi/token', [IntegrationController::class, 'storeToken'])->name('integrasi.token.store');
    Route::delete('/integrasi/token/{token}', [IntegrationController::class, 'destroyToken'])->name('integrasi.token.destroy');
});

/*
| OWNER: master data, gudang, laporan, akuntansi, persetujuan, audit.
*/
Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::resource('kategori', CategoryController::class)->parameters(['kategori' => 'category'])->except('show');
    Route::resource('supplier', SupplierController::class)->parameters(['supplier' => 'supplier']);

    Route::resource('barang', BarangController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);

    Route::resource('gudang', WarehouseController::class)->parameters(['gudang' => 'warehouse']);

    Route::get('/laporan', [\App\Http\Controllers\Admin\ReportCenterController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/data/{type}', [\App\Http\Controllers\Admin\ReportCenterController::class, 'show'])->name('laporan.data');
    Route::get('/laporan/data/{type}/csv', [\App\Http\Controllers\Admin\ReportCenterController::class, 'csv'])->name('laporan.data.csv');
    Route::get('/laporan/penjualan', [ReportController::class, 'sales'])->name('laporan.penjualan');
    Route::get('/laporan/laba-rugi', [ReportController::class, 'profitLoss'])->name('laporan.laba-rugi');
    Route::get('/laporan/neraca-saldo', [ReportController::class, 'trialBalance'])->name('laporan.neraca-saldo');
    Route::get('/laporan/buku-besar', [ReportController::class, 'ledger'])->name('laporan.buku-besar');
    Route::get('/laporan/jurnal', [ReportController::class, 'journal'])->name('laporan.jurnal');

    Route::resource('pengeluaran', ExpenseController::class)->parameters(['pengeluaran' => 'expense'])->only(['index', 'create', 'store', 'destroy']);



    Route::get('/laporan/otomatis', [AutoReportController::class, 'index'])->name('laporan.otomatis');
    Route::get('/laporan/otomatis/ekspor', [AutoReportController::class, 'export'])->name('laporan.otomatis.export');

    Route::get('/anomali', [AnomalyController::class, 'index'])->name('anomali.index');
    Route::post('/anomali/pindai', [AnomalyController::class, 'scan'])->name('anomali.scan');
    Route::patch('/anomali/{flag}', [AnomalyController::class, 'review'])->name('anomali.review');

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::post('/pembelian/{purchase}/setujui', [PurchaseController::class, 'approve'])->name('pembelian.approve');
    Route::post('/pembelian/{purchase}/tolak', [PurchaseController::class, 'reject'])->name('pembelian.reject');
    Route::post('/transfer-stok/{transfer}/setujui', [StockTransferController::class, 'approve'])->name('transfer-stok.approve');
    Route::post('/transfer-stok/{transfer}/tolak', [StockTransferController::class, 'reject'])->name('transfer-stok.reject');
    Route::post('/opname/{opname}/setujui', [OpnameController::class, 'approve'])->name('opname.approve');
    Route::post('/opname/{opname}/tolak', [OpnameController::class, 'reject'])->name('opname.reject');
    Route::post('/barang/massal', [BarangController::class, 'bulk'])->name('barang.bulk');

    Route::post('/pembelian/{purchase}/batal', [PurchaseController::class, 'cancel'])->name('pembelian.cancel');
    Route::delete('/stok-keluar/{stockOut}', [StockOutController::class, 'destroy'])->name('stok-keluar.destroy');
});

/*
| ADMIN & STAFF: operasional harian.
*/
Route::middleware(['auth', 'role:owner,staff'])->group(function () {
    Route::get('/barang/export/excel', [BarangController::class, 'exportExcel'])->name('barang.export.excel');
    Route::get('/barang/export/pdf', [BarangController::class, 'exportPdf'])->name('barang.export.pdf');
    Route::get('/barang', [BarangController::class, 'index'])->name('barang.index');
    Route::get('/barang/{barang}', [BarangController::class, 'show'])->name('barang.show');
    Route::get('/stok-menipis', [LowStockController::class, 'index'])->name('stok-menipis.index');

    Route::resource('pembelian', PurchaseController::class)->parameters(['pembelian' => 'purchase'])->only(['index', 'create', 'store', 'show']);
    Route::post('/pembelian/{purchase}/ajukan', [PurchaseController::class, 'submit'])->name('pembelian.submit');
    Route::get('/pembelian/{purchase}/terima', [PurchaseController::class, 'receiveForm'])->name('pembelian.receive.form');
    Route::post('/pembelian/{purchase}/terima', [PurchaseController::class, 'receive'])->name('pembelian.receive');

    Route::resource('penjualan', SaleController::class)->parameters(['penjualan' => 'sale'])->only(['index', 'create', 'store', 'show']);
    Route::post('/penjualan/{sale}/proses', [SaleController::class, 'complete'])->name('penjualan.complete');
    Route::post('/penjualan/{sale}/batal', [SaleController::class, 'cancel'])->name('penjualan.cancel');
    Route::post('/penjualan/{sale}/bayar', [SaleController::class, 'markPaid'])->name('penjualan.pay');
    Route::get('/penjualan/{sale}/retur', [SaleController::class, 'returnForm'])->name('penjualan.return.form');
    Route::post('/penjualan/{sale}/retur', [SaleController::class, 'returnStore'])->name('penjualan.return');
    Route::get('/penjualan/{sale}/struk', [SaleController::class, 'receipt'])->name('penjualan.receipt');

    Route::resource('stok-keluar', StockOutController::class)->parameters(['stok-keluar' => 'stockOut'])->only(['index', 'create', 'store']);
    Route::resource('transfer-stok', StockTransferController::class)->only(['index', 'create', 'store']);
    Route::post('/transfer-stok/{transfer}/batalkan', [StockTransferController::class, 'cancel'])->name('transfer-stok.cancel');

    Route::resource('opname', OpnameController::class)->parameters(['opname' => 'opname'])->only(['index', 'create', 'store', 'show']);
    Route::put('/opname/{opname}/hitung', [OpnameController::class, 'saveCounts'])->name('opname.counts');
    Route::post('/opname/{opname}/ajukan', [OpnameController::class, 'submit'])->name('opname.submit');

    Route::get('/kedaluwarsa', [ExpiryController::class, 'index'])->name('kedaluwarsa.index');

    Route::get('/restock', [RestockController::class, 'index'])->name('restock.index');
    Route::post('/restock/draft-po', [RestockController::class, 'createDrafts'])->name('restock.drafts');

    Route::get('/analitik', [AnalyticsController::class, 'index'])->name('analitik.index');
    Route::get('/analitik/ekspor', [AnalyticsController::class, 'export'])->name('analitik.export');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

    Route::get('/prediksi-stok', [ForecastController::class, 'index'])->name('prediksi.index');
    Route::get('/transaksi', [TransaksiController::class, 'index'])->name('transaksi.index');
});

// Invoice: dapat diakses pelanggan pemilik, staff, dan admin (otorisasi di controller lewat Gate view-sale).
Route::middleware('auth')->get('/penjualan/{sale}/invoice', [SaleController::class, 'invoice'])->name('penjualan.invoice');

require __DIR__.'/auth.php';
