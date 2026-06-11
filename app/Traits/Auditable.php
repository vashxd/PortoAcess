<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            AuditLog::record('updated', $model, $old, $changes);
        });

        static::deleted(function ($model) {
            AuditLog::record('deleted', $model, $model->getOriginal(), null);
        });
    }
}
