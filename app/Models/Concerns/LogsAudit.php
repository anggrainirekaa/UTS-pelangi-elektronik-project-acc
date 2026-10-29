<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat create/update/delete model ke audit_logs (siapa, kapan, nilai lama -> baru).
 * Atribut sensitif tidak pernah disimpan nilainya.
 */
trait LogsAudit
{
    /** Atribut yang tidak pernah dicatat nilainya. Model dapat menimpa metode ini. */
    protected static function auditNever(): array
    {
        return ['password', 'remember_token', 'token', 'secret', 'updated_at', 'created_at', 'deleted_at'];
    }

    public static function bootLogsAudit(): void
    {
        static::created(function (Model $model) {
            AuditLogger::log(static::auditName().'.created', static::auditLabel($model).' dibuat', $model, null, static::auditValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = array_diff_key($model->getChanges(), array_flip(static::auditNever()));
            $sensitive = array_intersect_key($model->getChanges(), array_flip(['password']));

            if (! $changes && ! $sensitive) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);

            AuditLogger::log(
                static::auditName().'.updated',
                static::auditLabel($model).' diubah'.($sensitive ? ' (password diganti)' : ''),
                $model,
                static::auditValues($old),
                static::auditValues($changes)
            );
        });

        static::deleted(function (Model $model) {
            AuditLogger::log(static::auditName().'.deleted', static::auditLabel($model).' dihapus', $model, static::auditValues($model->getOriginal()), null);
        });
    }

    protected static function auditName(): string
    {
        return strtolower(class_basename(static::class));
    }

    protected static function auditLabel(Model $model): string
    {
        $name = $model->getAttribute('name') ?? $model->getAttribute('title') ?? $model->getAttribute('code') ?? '#'.$model->getKey();

        return class_basename($model).' "'.$name.'"';
    }

    /** @param  array<string, mixed>  $values */
    protected static function auditValues(array $values): array
    {
        return array_diff_key($values, array_flip(static::auditNever()));
    }
}
