<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use LogsAudit;

    protected $fillable = ['code', 'name', 'address', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /**
     * Gudang default. Dibuat otomatis bila belum ada (instalasi baru / testing),
     * sehingga transaksi selalu punya gudang tujuan.
     */
    public static function default(): self
    {
        return static::where('is_default', true)->first()
            ?? static::first()
            ?? static::create(['code' => 'GDG-001', 'name' => 'Gudang Utama', 'is_default' => true]);
    }
}
