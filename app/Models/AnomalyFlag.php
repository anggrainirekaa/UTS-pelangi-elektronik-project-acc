<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyFlag extends Model
{
    public const TYPES = [
        'large_stock_change' => 'Perubahan stok sangat besar',
        'repeated_transaction' => 'Transaksi berulang',
        'negative_stock' => 'Stok negatif',
        'balance_mismatch' => 'Saldo tidak sesuai transaksi',
        'batch_mismatch' => 'Batch tidak sesuai saldo',
    ];

    protected $fillable = [
        'fingerprint', 'type', 'severity', 'title', 'detail', 'subject_type', 'subject_id', 'context',
        'status', 'reviewed_by', 'reviewed_at', 'review_note', 'detected_at',
    ];

    protected $casts = ['context' => 'array', 'reviewed_at' => 'datetime', 'detected_at' => 'datetime'];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
