<?php

namespace App\Models;

use App\Enums\CameraEventStatus;
use Illuminate\Database\Eloquent\Model;

class CameraEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'confidence' => 'decimal:2',
        'status' => CameraEventStatus::class,
    ];

    public function accessRecord()
    {
        return $this->belongsTo(AccessRecord::class);
    }
}
