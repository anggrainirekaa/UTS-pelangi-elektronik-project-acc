<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Purchase extends Model
{
    public const STATUS_DRAFT = 'draft';              // PO disusun, belum diajukan

    public const STATUS_PENDING = 'pending';          // diajukan, menunggu persetujuan admin

    public const STATUS_APPROVED = 'approved';        // disetujui, menunggu barang datang

    public const STATUS_PARTIAL = 'partially_received';

    public const STATUS_COMPLETED = 'completed';      // seluruh barang diterima

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'purchase_number', 'supplier_id', 'user_id', 'warehouse_id', 'total',
        'status', 'purchased_at', 'note', 'cancelled_at', 'cancelled_by', 'cancel_reason',
        'submitted_at', 'approved_at', 'approved_by', 'request_key',
    ];

    protected $casts = [
        'total' => 'integer',
        'purchased_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** Bisa menerima barang: sudah disetujui atau sebagian diterima. */
    public function canReceive(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIAL], true);
    }

    /** Pesanan yang masih berjalan (mengisi "stok dalam pesanan"). */
    public function isOpenOrder(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIAL], true);
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Menunggu persetujuan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_PARTIAL => 'Diterima sebagian',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ][$this->status] ?? $this->status;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Admin melihat semua pembelian; staff hanya pembelian yang ia buat sendiri.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isManagement() ? $query : $query->where('user_id', $user->id);
    }
}
