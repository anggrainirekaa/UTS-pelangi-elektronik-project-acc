<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Nomor dokumen unik tanpa race condition: baris dibuat dengan nomor sementara lalu
 * nomor final diturunkan dari primary key (mis. SL-20261022-00042).
 */
class NumberGenerator
{
    public static function temporary(): string
    {
        return 'TMP-'.Str::uuid();
    }

    public static function finalize(Model $model, string $prefix, string $column, ?Carbon $date = null): void
    {
        $date ??= now();

        $model->forceFill([
            $column => $prefix.'-'.$date->format('Ymd').'-'.str_pad((string) $model->getKey(), 5, '0', STR_PAD_LEFT),
        ])->save();
    }
}
