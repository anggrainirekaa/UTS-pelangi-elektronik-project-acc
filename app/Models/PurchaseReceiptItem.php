<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptItem extends Model
{
    protected $fillable = ['purchase_receipt_id', 'purchase_item_id', 'barang_id', 'quantity', 'unit_cost', 'batch_number', 'expiry_date'];

    protected $casts = ['expiry_date' => 'date'];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class)->withTrashed();
    }
}
