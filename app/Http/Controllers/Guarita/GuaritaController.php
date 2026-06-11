<?php

namespace App\Http\Controllers\Guarita;

use App\Enums\AccessStatus;
use App\Enums\CameraEventStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use App\Models\CameraEvent;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\Price;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\PriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GuaritaController extends Controller
{
    public function painel()
    {
        return Inertia::render('Guarita/Painel', [
            'entryTypes' => EntryType::where('active', true)->get(),
            'categories' => VehicleCategory::where('active', true)->orderBy('name')->get(),
            'companies' => Company::where('active', true)->orderBy('name')->get(['id', 'name', 'discount_percent']),
            'priceMatrix' => $this->priceMatrix(),
            'pix' => [
                'key' => config('portoaccess.pix_key'),
                'merchant' => config('portoaccess.pix_merchant_name'),
            ],
        ]);
    }

    /** Polling JSON: eventos pendentes + estado do pátio (RNF01 / tempo real). */
    public function eventos(Request $request)
    {
        $events = CameraEvent::where('status', CameraEventStatus::Pendente)
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn (CameraEvent $e) => $this->enrich($e));

        $patioCount = AccessRecord::where('status', AccessStatus::NoPatio)->count();

        $overstays = AccessRecord::with(['vehicle', 'entryType'])
            ->where('status', AccessStatus::NoPatio)
            ->whereHas('entryType', fn ($q) => $q->whereNotNull('max_stay_minutes'))
            ->get()
            ->filter->isOverstay()
            ->values()
            ->map(fn ($r) => [
                'id' => $r->id,
                'plate' => $r->vehicle->plate,
                'entry_type' => $r->entryType->name,
                'visitor_name' => $r->visitor_name,
                'entered_at' => $r->entered_at->toIso8601String(),
                'stay_minutes' => $r->stayMinutes(),
                'limit_minutes' => $r->entryType->max_stay_minutes,
            ]);

        return response()->json([
            'events' => $events,
            'patio_count' => $patioCount,
            'overstays' => $overstays,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function descartarEvento(CameraEvent $cameraEvent)
    {
        $cameraEvent->update(['status' => CameraEventStatus::Descartado]);

        \App\Models\AuditLog::record('camera_event_discarded', $cameraEvent);

        return back()->with('success', 'Leitura descartada.');
    }

    /** Dados do veículo/registro vinculados a um evento de câmera. */
    private function enrich(CameraEvent $e): array
    {
        $vehicle = $e->plate
            ? Vehicle::with(['category', 'activeAuthorization.company'])->where('plate', $e->plate)->first()
            : null;

        $openRecord = null;
        $mismatch = false;

        if ($vehicle) {
            $record = AccessRecord::with(['entryType', 'payments'])
                ->where('vehicle_id', $vehicle->id)
                ->where('status', AccessStatus::NoPatio)
                ->latest('entered_at')
                ->first();

            if ($record) {
                $openRecord = [
                    'id' => $record->id,
                    'entered_at' => $record->entered_at->toIso8601String(),
                    'entry_type' => $record->entryType->name,
                    'entry_type_id' => $record->entry_type_id,
                    'is_paid' => $record->entryType->is_paid,
                    'amount_due' => (float) $record->amount_due,
                    'discount' => (float) $record->discount_amount,
                    'paid' => $record->totalPaid(),
                    'balance' => $record->balanceDue(),
                    'stay_minutes' => $record->stayMinutes(),
                    'company_id' => $record->company_id,
                ];
            }

            $colorDiff = $vehicle->color && $e->color
                && mb_strtolower(trim($vehicle->color)) !== mb_strtolower(trim($e->color));
            $modelDiff = $vehicle->model && $e->model
                && ! str_contains(mb_strtolower($e->model), mb_strtolower(trim($vehicle->model)))
                && ! str_contains(mb_strtolower($vehicle->model), mb_strtolower(trim($e->model)));
            $mismatch = $colorDiff || $modelDiff;
        }

        $auth = $vehicle?->activeAuthorization;

        return [
            'id' => $e->id,
            'camera' => $e->camera,
            'plate' => $e->plate,
            'color' => $e->color,
            'model' => $e->model,
            'brand' => $e->brand,
            'confidence' => $e->confidence !== null ? (float) $e->confidence : null,
            'photo_url' => $e->photo_path ? Storage::url($e->photo_path) : null,
            'occurred_at' => $e->occurred_at->toIso8601String(),
            'vehicle' => $vehicle ? [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'category_id' => $vehicle->vehicle_category_id,
                'category' => $vehicle->category?->name,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'color' => $vehicle->color,
                'owner_name' => $vehicle->owner_name,
            ] : null,
            'authorization' => $auth ? [
                'type' => $auth->type,
                'employee_name' => $auth->employee_name,
                'company_id' => $auth->company_id,
                'company' => $auth->company?->name,
            ] : null,
            'open_record' => $openRecord,
            'mismatch' => $mismatch,
        ];
    }

    /** Preços vigentes: { entry_type_id: { category_id: amount } } */
    private function priceMatrix(): array
    {
        $today = now();
        $matrix = [];

        Price::whereDate('valid_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today))
            ->orderBy('valid_from')
            ->get()
            ->each(function (Price $p) use (&$matrix) {
                $matrix[$p->entry_type_id][$p->vehicle_category_id] = (float) $p->amount;
            });

        return $matrix;
    }
}
