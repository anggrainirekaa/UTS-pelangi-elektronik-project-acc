<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use LogsAudit;

    protected $fillable = ['code', 'name', 'symbol', 'decimals', 'rate', 'is_base', 'is_active'];

    protected $casts = ['rate' => 'float', 'is_base' => 'boolean', 'is_active' => 'boolean', 'decimals' => 'integer'];

    /** Mata uang dasar (IDR). Semua transaksi disimpan dalam mata uang ini. */
    public static function base(): self
    {
        return static::where('is_base', true)->first()
            ?? static::firstOrCreate(
                ['code' => 'IDR'],
                ['name' => 'Rupiah Indonesia', 'symbol' => 'Rp', 'decimals' => 0, 'rate' => 1, 'is_base' => true]
            );
    }
}
