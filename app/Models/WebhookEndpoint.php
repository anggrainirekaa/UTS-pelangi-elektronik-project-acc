<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    public const EVENTS = [
        'sale.completed' => 'Penjualan selesai',
        'sale.cancelled' => 'Penjualan dibatalkan',
        'sale.returned' => 'Retur penjualan',
        'purchase.completed' => 'Pembelian selesai',
        'purchase.cancelled' => 'Pembelian dibatalkan',
        'stock.low' => 'Stok menipis',
    ];

    protected $fillable = ['name', 'url', 'secret', 'events', 'is_active'];

    protected $casts = ['events' => 'array', 'is_active' => 'boolean'];

    protected $hidden = ['secret'];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
