<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Kartu stok: satu baris per perubahan saldo (bertanda + masuk / - keluar). */
class StockMovement extends Model
{
    public const TYPES = [
        'opening' => 'Saldo awal',
        'purchase' => 'Penerimaan pembelian',
        'purchase_cancel' => 'Pembatalan pembelian',
        'sale' => 'Penjualan',
        'sale_cancel' => 'Pembatalan penjualan',
        'sale_return' => 'Retur penjualan',
        'adjustment' => 'Penyesuaian stok',
        'transfer_out' => 'Transfer keluar',
        'transfer_in' => 'Transfer masuk',
        'opname' => 'Stock opname',
        'manual' => 'Penyesuaian manual',
    ];

    protected $fillable = [
        'barang_id', 'warehouse_id', 'batch_id', 'type', 'quantity', 'balance_after', 'total_after',
        'reference_type', 'reference_id', 'user_id', 'note', 'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'integer', 'balance_after' => 'integer', 'total_after' => 'integer', 'occurred_at' => 'datetime',
    ];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
