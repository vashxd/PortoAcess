<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntryType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EntryTypeController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/TiposEntrada', [
            'entryTypes' => EntryType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        EntryType::create($this->validated($request) + ['active' => true]);

        return back()->with('success', 'Tipo de entrada criado.');
    }

    public function update(Request $request, EntryType $entryType)
    {
        $entryType->update($this->validated($request, true));

        return back()->with('success', 'Tipo de entrada atualizado.');
    }

    public function destroy(EntryType $entryType)
    {
        $entryType->update(['active' => false]);

        return back()->with('success', 'Tipo de entrada inativado (registros históricos preservados).');
    }

    private function validated(Request $request, bool $withActive = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'is_paid' => ['required', 'boolean'],
            'charge_moment' => ['nullable', 'required_if:is_paid,true', 'in:entrada,saida'],
            'max_stay_minutes' => ['nullable', 'integer', 'min:1'],
            'requires_visitor_info' => ['required', 'boolean'],
        ];

        if ($withActive) {
            $rules['active'] = ['required', 'boolean'];
        }

        $data = $request->validate($rules);

        if (! $data['is_paid']) {
            $data['charge_moment'] = null;
        }

        return $data;
    }
}
