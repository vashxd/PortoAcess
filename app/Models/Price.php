<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function entryType()
    {
        return $this->belongsTo(EntryType::class);
    }

    public function vehicleCategory()
    {
        return $this->belongsTo(VehicleCategory::class);
    }
}
