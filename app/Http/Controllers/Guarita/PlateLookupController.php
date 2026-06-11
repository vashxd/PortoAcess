<?php

namespace App\Http\Controllers\Guarita;

use App\Enums\AccessStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlateLookupController extends Controller
{
    /** Tela de consulta de placa com histórico do veículo. */
    public function index(Request $request)
    {
        $plate = $request->query('placa') ? Vehicle::normalizePlate($request->query('placa')) : null;
        $vehicle = null;
        $history = [];

        if ($plate) {
            $vehicle = Vehicle::with(['category', 'activeAuthorization.company'])
                ->where('plate', $plate)->first();

            if ($vehicle) {
                $history = AccessRecord::with(['entryType', 'vehicleCategory', 'company', 'payments', 'operatorIn', 'operatorOut'])
                    ->where('vehicle_id', $vehicle->id)
                    ->orderByDesc('entered_at')
                    ->limit(50)
                    ->get()
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'entered_at' => $r->entered_at->toIso8601String(),
                        'exited_at' => $r->exited_at?->toIso8601String(),
                        'entry_type' => $r->entryType->name,
                        'category' => $r->vehicleCategory->name,
                        'company' => $r->company?->name,
                        'status' => $r->status->value,
                        'status_label' => $r->status->label(),
                        'amount_due' => (float) $r->amount_due,
                        'discount' => (float) $r->discount_amount,
                        'paid' => $r->totalPaid(),
                        'manual_entry' => $r->manual_entry,
                        'stay_minutes' => $r->stayMinutes(),
                        'operator_in' => $r->operatorIn?->name,
                        'operator_out' => $r->operatorOut?->name,
                    ]);
            }
        }

        return Inertia::render('Guarita/Consulta', [
            'placa' => $plate,
            'vehicle' => $vehicle,
            'history' => $history,
        ]);
    }

    /** JSON para pré-preencher o formulário de entrada manual. */
    public function lookup(Request $request)
    {
        $plate = Vehicle::normalizePlate((string) $request->query('plate', ''));

        $vehicle = Vehicle::with(['category', 'activeAuthorization.company'])
            ->where('plate', $plate)->first();

        $openRecord = $vehicle
            ? AccessRecord::with(['entryType'])
                ->where('vehicle_id', $vehicle->id)
                ->where('status', AccessStatus::NoPatio)
                ->latest('entered_at')
                ->first()
            : null;

        return response()->json([
            'vehicle' => $vehicle,
            'authorization' => $vehicle?->activeAuthorization,
            'open_record' => $openRecord ? [
                'id' => $openRecord->id,
                'entered_at' => $openRecord->entered_at->toIso8601String(),
                'entry_type' => $openRecord->entryType->name,
                'is_paid' => $openRecord->entryType->is_paid,
                'amount_due' => (float) $openRecord->amount_due,
                'discount' => (float) $openRecord->discount_amount,
                'paid' => $openRecord->totalPaid(),
                'balance' => $openRecord->balanceDue(),
                'company_id' => $openRecord->company_id,
            ] : null,
        ]);
    }
}
