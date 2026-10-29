<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token', 'type', 'last_used_at', 'expires_at'];

    protected $hidden = ['token'];

    protected $casts = ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Buat token baru. Token asli hanya dikembalikan sekali; database menyimpan hash sha256-nya.
     *
     * @return array{0: ApiToken, 1: string} [model, plainTextToken]
     */
    public static function issue(User $user, string $name, string $type = 'mobile', ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = Str::random(48);

        $model = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => hash('sha256', $plain),
            'type' => $type,
            'expires_at' => $expiresAt,
        ]);

        return [$model, $model->id.'|'.$plain];
    }

    /** Cari token dari string "id|token". */
    public static function findFromPlain(string $plain, ?string $type = null): ?self
    {
        if (! str_contains($plain, '|')) {
            return null;
        }

        [$id, $secret] = explode('|', $plain, 2);

        $token = static::with('user')->find($id);

        if (! $token || ! hash_equals($token->token, hash('sha256', $secret))) {
            return null;
        }

        if ($type !== null && $token->type !== $type) {
            return null;
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }
}
