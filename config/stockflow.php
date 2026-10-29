<?php

/*
| Aturan bisnis Pelangi Elektronik yang dapat diatur lewat .env tanpa mengubah kode.
*/
return [
    // Staff tidak boleh memberi diskon di atas persentase ini (admin tanpa batas).
    'max_staff_discount_percent' => (float) env('STOCKFLOW_MAX_STAFF_DISCOUNT', 20),

    // Batas hari retur penjualan untuk staff (admin dapat melampaui).
    'return_window_days' => (int) env('STOCKFLOW_RETURN_DAYS', 7),

    // Transfer stok oleh staff dengan jumlah >= nilai ini perlu persetujuan admin.
    'transfer_approval_threshold' => (int) env('STOCKFLOW_TRANSFER_APPROVAL_QTY', 100),

    // Deteksi anomali.
    'anomaly' => [
        'large_change_min_qty' => (int) env('STOCKFLOW_ANOMALY_MIN_QTY', 50),     // abaikan perubahan kecil
        'large_change_multiplier' => (float) env('STOCKFLOW_ANOMALY_MULTIPLIER', 5), // x rata-rata perubahan produk
        'repeat_window_minutes' => (int) env('STOCKFLOW_ANOMALY_REPEAT_MINUTES', 5),
        'lookback_days' => (int) env('STOCKFLOW_ANOMALY_LOOKBACK_DAYS', 30),
    ],

    // Rekomendasi restock.
    'restock' => [
        'history_days' => (int) env('STOCKFLOW_RESTOCK_HISTORY_DAYS', 30),
        'coverage_days' => (int) env('STOCKFLOW_RESTOCK_COVERAGE_DAYS', 14),
        'min_history_days' => 7,   // di bawah ini hasil ditandai "Belum cukup data"
        'min_sales_events' => 3,
    ],
];
