<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $model = null, ?array $old = null, ?array $new = null): void
    {
        // Senhas e tokens nunca vão para o log
        foreach (['password', 'remember_token'] as $secret) {
            unset($old[$secret], $new[$secret]);
        }

        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity' => $model ? class_basename($model) : null,
            'entity_id' => $model?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
