<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'method' => PaymentMethod::class,
    ];

    public function accessRecord()
    {
        return $this->belongsTo(AccessRecord::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
