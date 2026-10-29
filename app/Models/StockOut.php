<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penyesuaian stok keluar non-penjualan (pemakaian internal, rusak/hilang, dll).
 * Penjualan ke pelanggan dicatat lewat modul Penjualan (Sale + SaleItem).
 */
class StockOut extends Model
{
    use HasFactory;

    public const PURPOSES = ['internal_use', 'damaged', 'expired', 'other'];

    protected $fillable = [
        'barang_id',
        'warehouse_id',
        'user_id',
        'quantity',
        'selling_price',
        'date',
        'purpose',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'integer',
        'selling_price' => 'integer',
    ];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPurposeLabelAttribute(): string
    {
        return match ($this->purpose) {
            'sale' => 'Dijual',
            'internal_use' => 'Pemakaian Internal',
            'damaged' => 'Rusak/Hilang',
            'expired' => 'Kedaluwarsa',
            default => 'Lainnya',
        };
    }
}
