<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, LogsAudit, Notifiable;

    public const ROLE_OWNER = 'owner';

    /** Alias lama: role pengelola penuh kini bernama owner (satu role, tanpa admin terpisah). */
    public const ROLE_ADMIN = self::ROLE_OWNER;

    public const ROLE_STAFF = 'staff';

    public const ROLE_PELANGGAN = 'pelanggan';

    public const ROLES = [self::ROLE_OWNER, self::ROLE_STAFF, self::ROLE_PELANGGAN];

    /**
     * Sengaja tidak ada 'role' di sini: role tidak boleh diisi lewat mass-assignment
     * dari input pengguna (mencegah privilege escalation saat registrasi/profil).
     * Role hanya diatur eksplisit oleh Admin (UserController) atau seeder/factory.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Owner (pengelola penuh). Alias isAdmin() dipertahankan agar kode lama tetap berjalan. */
    public function isManagement(): bool
    {
        return $this->isAdmin();
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isPelanggan(): bool
    {
        return $this->role === self::ROLE_PELANGGAN;
    }

    /** Admin/Owner atau staff (pengguna internal). */
    public function isInternal(): bool
    {
        return $this->isManagement() || $this->isStaff();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_OWNER => 'Owner',
            self::ROLE_STAFF => 'Staff',
            default => 'Pelanggan',
        };
    }

    /** Transaksi penjualan yang dibuat user ini. */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Pesanan di mana user ini adalah pembelinya. */
    public function orders(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }
}
