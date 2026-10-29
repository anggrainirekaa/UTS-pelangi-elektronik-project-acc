<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sale extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const CHANNEL_POS = 'pos';

    public const CHANNEL_ONLINE = 'online';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    protected $fillable = [
        'sale_number', 'user_id', 'customer_id', 'warehouse_id', 'channel', 'subtotal', 'discount', 'total', 'status', 'request_key',
        'payment_method', 'payment_status', 'payment_reference', 'paid_at', 'sold_at',
        'completed_at', 'shipping_address', 'note', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'total' => 'integer',
        'subtotal' => 'integer',
        'discount' => 'integer',
        'paid_at' => 'datetime',
        'sold_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu Diproses',
            self::STATUS_COMPLETED => 'Selesai',
            default => 'Dibatalkan',
        };
    }

    /**
     * Aturan pembatalan:
     *  - Admin: boleh membatalkan transaksi pending maupun selesai.
     *  - Staff: hanya boleh menolak/membatalkan pesanan pending yang boleh ia lihat.
     *  - Pelanggan: hanya pesanan pending miliknya sendiri.
     */
    public function canBeCancelledBy(User $user): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        if ($user->isManagement()) {
            return true;
        }

        if (! $this->isPending()) {
            return false;
        }

        return $user->isStaff()
            ? ($this->user_id === $user->id || $this->channel === self::CHANNEL_ONLINE)
            : $this->customer_id === $user->id;
    }

    /**
     * Admin melihat semua penjualan. Staff melihat penjualan kasir yang ia buat sendiri
     * serta seluruh pesanan online (karena staff yang memprosesnya).
     * Pelanggan hanya melihat pesanannya sendiri.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isManagement()) {
            return $query;
        }

        if ($user->isStaff()) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('channel', self::CHANNEL_ONLINE);
            });
        }

        return $query->where('customer_id', $user->id);
    }
}
