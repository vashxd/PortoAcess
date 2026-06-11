<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function authorizedVehicles()
    {
        return $this->hasMany(AuthorizedVehicle::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function accessRecords()
    {
        return $this->hasMany(AccessRecord::class);
    }

    /** Débito faturado ainda não incluído em fatura. */
    public function pendingBilledTotal(): float
    {
        return (float) $this->accessRecords()
            ->where('status', '!=', 'cancelado')
            ->whereHas('payments', fn ($q) => $q->where('method', 'faturado'))
            ->whereDoesntHave('invoiceItems')
            ->with('payments')
            ->get()
            ->sum(fn ($r) => $r->payments->where('method', \App\Enums\PaymentMethod::Faturado)->sum('amount'));
    }
}
