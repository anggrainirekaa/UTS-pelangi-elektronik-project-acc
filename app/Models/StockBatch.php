<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBatch extends Model
{
    protected $fillable = ['barang_id', 'warehouse_id', 'batch_number', 'expiry_date', 'quantity', 'received_at'];

    protected $casts = ['expiry_date' => 'date', 'quantity' => 'integer', 'received_at' => 'datetime'];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->where('quantity', '>', 0);
    }

    /** Urutan FEFO: kedaluwarsa paling dekat dulu, tanpa tanggal paling akhir. */
    public function scopeFefo(Builder $q): Builder
    {
        return $q->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('received_at')->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }

    public function daysToExpiry(): ?int
    {
        return $this->expiry_date ? (int) today()->diffInDays($this->expiry_date, false) : null;
    }
}
