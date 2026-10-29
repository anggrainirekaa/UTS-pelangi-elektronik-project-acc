<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'barang_id', 'quantity', 'unit_price', 'unit_cost', 'subtotal'];

    protected $casts = [
        'quantity' => 'integer', 'unit_price' => 'integer', 'unit_cost' => 'integer', 'subtotal' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class)->withTrashed();
    }
}
