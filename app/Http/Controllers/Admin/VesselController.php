<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VesselType;
use App\Http\Controllers\Controller;
use App\Models\Vessel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VesselController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Embarcacoes', [
            'vessels' => Vessel::with(['schedules' => fn ($q) => $q->orderBy('departure_time')])
                ->orderBy('name')
                ->get(),
            'vesselTypes' => VesselType::options(),
        ]);
    }

    public function store(Request $request)
    {
        Vessel::create($this->validated($request) + ['active' => true]);

        return back()->with('success', 'Embarcação cadastrada.');
    }

    public function update(Request $request, Vessel $vessel)
    {
        $vessel->update($this->validated($request, true));

        return back()->with('success', 'Embarcação atualizada.');
    }

    public function destroy(Vessel $vessel)
    {
        $vessel->update(['active' => false]);

        return back()->with('success', 'Embarcação inativada (histórico preservado).');
    }

    private function validated(Request $request, bool $withActive = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:balsa,lancha,rebocador,outro'],
            'registration' => ['nullable', 'string', 'max:120'],
            'operator' => ['nullable', 'string', 'max:120'],
            'default_destination' => ['nullable', 'string', 'max:120'],
            'capacity_vehicles' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($withActive) {
            $rules['active'] = ['required', 'boolean'];
        }

        return $request->validate($rules);
    }
}
