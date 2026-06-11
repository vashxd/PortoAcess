<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'total' => 'decimal:2',
        'status' => InvoiceStatus::class,
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public static function nextNumber(): string
    {
        $seq = (int) (static::max('id') ?? 0) + 1;

        return sprintf('FAT-%s-%04d', now()->format('Y'), $seq);
    }
}
