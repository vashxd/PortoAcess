<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DepartureStatus;
use App\Http\Controllers\Controller;
use App\Models\Vessel;
use App\Models\VesselDeparture;
use App\Services\VesselService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class VesselDepartureController extends Controller
{
    public function __construct(private VesselService $vessels) {}

    public function index(Request $request)
    {
        $from = $request->date('from') ?: Carbon::today();
        $to = $request->date('to') ?: Carbon::today()->addDays(7);

        $departures = VesselDeparture::with('vessel')
            ->withCount('accessRecords')
            ->whereBetween('departure_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('departure_at')
            ->get()
            ->map(fn (VesselDeparture $d) => [
                'id' => $d->id,
                'vessel_name' => $d->vessel->name,
                'vessel_type' => $d->vessel->type->value,
                'departure_date' => $d->departure_date->toDateString(),
                'departure_time' => $d->departure_time,
                'departure_at' => $d->departure_at->toIso8601String(),
                'destination' => $d->destination,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'vehicles' => $d->access_records_count,
                'generated' => $d->vessel_schedule_id !== null,
            ]);

        return Inertia::render('Admin/Viagens', [
            'departures' => $departures,
            'vessels' => Vessel::where('active', true)->orderBy('name')->get(['id', 'name', 'default_destination']),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    /** Gera as viagens da grade para o intervalo (padrão: próximos 14 dias). */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $created = $this->vessels->generateDepartures(
            Carbon::today(),
            Carbon::today()->addDays($data['days'] ?? 14),
        );

        return back()->with('success', "Viagens geradas a partir da grade: {$created} nova(s).");
    }

    /** Viagem avulsa (fora da grade). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'vessel_id' => ['required', 'exists:vessels,id'],
            'departure_date' => ['required', 'date'],
            'departure_time' => ['required', 'date_format:H:i'],
            'destination' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $departureAt = Carbon::parse($data['departure_date'].' '.$data['departure_time']);

        VesselDeparture::updateOrCreate(
            ['vessel_id' => $data['vessel_id'], 'departure_at' => $departureAt],
            [
                'departure_date' => $data['departure_date'],
                'departure_time' => $data['departure_time'],
                'destination' => $data['destination'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => DepartureStatus::Agendada,
            ],
        );

        return back()->with('success', 'Viagem avulsa registrada.');
    }

    public function update(Request $request, VesselDeparture $departure)
    {
        $data = $request->validate([
            'status' => ['required', 'in:agendada,embarcando,encerrada,cancelada'],
            'destination' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $departure->update($data);

        return back()->with('success', 'Viagem atualizada.');
    }

    public function destroy(VesselDeparture $departure)
    {
        $departure->update(['status' => DepartureStatus::Cancelada]);

        return back()->with('success', 'Viagem cancelada.');
    }
}
