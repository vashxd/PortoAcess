<?php

namespace App\Models;

use App\Enums\VesselType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Vessel extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'type' => VesselType::class,
        'capacity_vehicles' => 'integer',
        'active' => 'boolean',
    ];

    public function schedules()
    {
        return $this->hasMany(VesselSchedule::class);
    }

    public function departures()
    {
        return $this->hasMany(VesselDeparture::class);
    }

    public function accessRecords()
    {
        return $this->hasMany(AccessRecord::class);
    }
}
