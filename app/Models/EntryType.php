<?php

namespace App\Models;

use App\Enums\ChargeMoment;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class EntryType extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_visitor_info' => 'boolean',
        'active' => 'boolean',
        'charge_moment' => ChargeMoment::class,
    ];

    public function prices()
    {
        return $this->hasMany(Price::class);
    }
}
