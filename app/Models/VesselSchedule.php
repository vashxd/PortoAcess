<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class VesselSchedule extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'days_of_week' => 'array',
        'active' => 'boolean',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function departures()
    {
        return $this->hasMany(VesselDeparture::class);
    }

    /** A grade opera nesta data? (Carbon dayOfWeek: 0=domingo … 6=sábado) */
    public function runsOn(CarbonInterface $date): bool
    {
        return $this->active && in_array((int) $date->dayOfWeek, array_map('intval', $this->days_of_week ?? []), true);
    }
}
