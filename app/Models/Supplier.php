<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, LogsAudit;

    protected $fillable = [
        'name',
        'phone',
        'contact_person',
        'email',
        'address',
        'lead_time_days',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    /**
     * Relasi: satu supplier memiliki banyak barang.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function barangs(): HasMany
    {
        return $this->hasMany(Barang::class);
    }
}