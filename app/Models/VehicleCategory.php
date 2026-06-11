<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class VehicleCategory extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];

    public function prices()
    {
        return $this->hasMany(Price::class);
    }
}
