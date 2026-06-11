<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class AuthorizedVehicle extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'valid_until' => 'date',
        'active' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
