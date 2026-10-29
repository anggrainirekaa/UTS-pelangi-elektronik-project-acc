<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Pencatat audit trail. Kegagalan mencatat tidak boleh menggagalkan transaksi bisnis. */
class AuditLogger
{
    public static function log(string $event, string $description, ?Model $subject = null, ?array $old = null, ?array $new = null): void
    {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'event' => $event,
                'description' => mb_substr($description, 0, 500),
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit log gagal ditulis', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
