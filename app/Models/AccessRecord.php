<?php

namespace App\Models;

use App\Enums\AccessStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class AccessRecord extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'entered_at' => 'datetime',
        'exited_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount_due' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'plate_read_confidence' => 'decimal:2',
        'manual_entry' => 'boolean',
        'exit_without_entry' => 'boolean',
        'color_model_mismatch' => 'boolean',
        'status' => AccessStatus::class,
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function entryType()
    {
        return $this->belongsTo(EntryType::class);
    }

    public function vehicleCategory()
    {
        return $this->belongsTo(VehicleCategory::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function operatorIn()
    {
        return $this->belongsTo(User::class, 'operator_in_id');
    }

    public function operatorOut()
    {
        return $this->belongsTo(User::class, 'operator_out_id');
    }

    public function cancelRequester()
    {
        return $this->belongsTo(User::class, 'cancel_requested_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function totalPaid(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function balanceDue(): float
    {
        return round((float) $this->amount_due - (float) $this->discount_amount - $this->totalPaid(), 2);
    }

    public function stayMinutes(): ?int
    {
        if (! $this->entered_at) {
            return null;
        }

        return (int) $this->entered_at->diffInMinutes($this->exited_at ?? now());
    }

    public function isOverstay(): bool
    {
        $limit = $this->entryType?->max_stay_minutes;

        return $limit !== null
            && $this->status === AccessStatus::NoPatio
            && $this->stayMinutes() > $limit;
    }
}
