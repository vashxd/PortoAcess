<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use Auditable;

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function authorizations()
    {
        return $this->hasMany(AuthorizedVehicle::class);
    }

    public function accessRecords()
    {
        return $this->hasMany(AccessRecord::class);
    }

    public function activeAuthorization()
    {
        return $this->hasOne(AuthorizedVehicle::class)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()));
    }

    public static function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }
}
