<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function accessRecord()
    {
        return $this->belongsTo(AccessRecord::class);
    }
}
