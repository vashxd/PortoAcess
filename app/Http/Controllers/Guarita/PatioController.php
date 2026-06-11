<?php

namespace App\Http\Controllers\Guarita;

use App\Enums\AccessStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PatioController extends Controller
{
    /** Veículos atualmente dentro do porto (RF08). */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('busca', ''));

        $records = AccessRecord::with(['vehicle', 'entryType', 'vehicleCategory', 'company', 'payments'])
            ->where('status', AccessStatus::NoPatio)
            ->when($search !== '', fn ($q) => $q->whereHas(
                'vehicle',
                fn ($v) => $v->where('plate', 'like', '%'.strtoupper($search).'%'),
            ))
            ->orderBy('entered_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'plate' => $r->vehicle->plate,
                'category' => $r->vehicleCategory->name,
                'entry_type' => $r->entryType->name,
                'is_paid' => $r->entryType->is_paid,
                'company' => $r->company?->name,
                'visitor_name' => $r->visitor_name,
                'entered_at' => $r->entered_at->toIso8601String(),
                'stay_minutes' => $r->stayMinutes(),
                'overstay' => $r->isOverstay(),
                'balance' => $r->balanceDue(),
                'manual_entry' => $r->manual_entry,
                'mismatch' => $r->color_model_mismatch,
                'cancel_requested' => $r->cancel_requested_at !== null,
            ]);

        return Inertia::render('Guarita/Patio', [
            'records' => $records,
            'busca' => $search,
        ]);
    }
}
