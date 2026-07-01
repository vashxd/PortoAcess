<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VesselSchedule;
use Illuminate\Http\Request;

class VesselScheduleController extends Controller
{
    public function store(Request $request)
    {
        VesselSchedule::create($this->validated($request) + ['active' => true]);

        return back()->with('success', 'Horário adicionado à grade.');
    }

    public function update(Request $request, VesselSchedule $schedule)
    {
        $schedule->update($this->validated($request, true));

        return back()->with('success', 'Horário atualizado.');
    }

    public function destroy(VesselSchedule $schedule)
    {
        $schedule->delete();

        return back()->with('success', 'Horário removido da grade.');
    }

    private function validated(Request $request, bool $withActive = false): array
    {
        $rules = [
            'vessel_id' => ['required', 'exists:vessels,id'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'departure_time' => ['required', 'date_format:H:i'],
            'destination' => ['nullable', 'string', 'max:120'],
        ];

        if ($withActive) {
            $rules['active'] = ['required', 'boolean'];
        }

        return $request->validate($rules);
    }
}
