<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi terpusat (database). Mendukung dedupe berdasarkan "key" agar kondisi yang sama
 * (mis. stok rendah produk X) tidak menumpuk berkali-kali.
 */
class NotificationService
{
    /**
     * @param  array<int, string>  $roles
     */
    public function toRoles(array $roles, string $kind, string $title, string $message, ?string $url = null, ?string $key = null, string $level = 'info'): void
    {
        if ($key !== null && $this->recentlySent($key)) {
            return;
        }

        $this->send(User::whereIn('role', $roles)->get(), $kind, $title, $message, $url, $key, $level);
    }

    public function toUser(?User $user, string $kind, string $title, string $message, ?string $url = null, ?string $key = null, string $level = 'info'): void
    {
        if (! $user) {
            return;
        }

        $this->send(collect([$user]), $kind, $title, $message, $url, $key, $level);
    }

    /** Tandai notifikasi berkunci tertentu sebagai terbaca (kondisi sudah teratasi). */
    public function resolve(string ...$keys): void
    {
        DatabaseNotification::whereNull('read_at')->whereIn('data->key', $keys)->update(['read_at' => now()]);
    }

    private function recentlySent(string $key): bool
    {
        return DatabaseNotification::where('data->key', $key)
            ->where(fn ($q) => $q->whereNull('read_at')->orWhere('created_at', '>', now()->subDay()))
            ->exists();
    }

    private function send($users, string $kind, string $title, string $message, ?string $url, ?string $key, string $level): void
    {
        try {
            foreach ($users as $user) {
                $user->notify(new SystemNotification($kind, $title, $message, $url, $key, $level));
            }
        } catch (Throwable $e) {
            // Notifikasi tidak boleh menggagalkan transaksi bisnis.
            Log::warning('Notifikasi gagal dikirim', ['kind' => $kind, 'error' => $e->getMessage()]);
        }
    }
}
