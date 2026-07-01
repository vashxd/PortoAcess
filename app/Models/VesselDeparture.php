<?php

namespace App\Models;

use App\Enums\DepartureStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class VesselDeparture extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'departure_date' => 'date',
        'departure_at' => 'datetime',
        'status' => DepartureStatus::class,
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function schedule()
    {
        return $this->belongsTo(VesselSchedule::class, 'vessel_schedule_id');
    }

    public function accessRecords()
    {
        return $this->hasMany(AccessRecord::class);
    }

    /** Nº de veículos já vinculados a esta partida (informativo). */
    public function vehicleCount(): int
    {
        return $this->accessRecords()->count();
    }
}
