<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'barang_id', 'quantity', 'received_quantity', 'unit_cost', 'subtotal'];

    protected $casts = ['quantity' => 'integer', 'received_quantity' => 'integer', 'unit_cost' => 'integer', 'subtotal' => 'integer'];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function remaining(): int
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class)->withTrashed();
    }
}
