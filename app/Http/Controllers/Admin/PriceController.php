<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntryType;
use App\Models\Price;
use App\Models\VehicleCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PriceController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Precos', [
            'prices' => Price::with(['entryType:id,name', 'vehicleCategory:id,name'])
                ->orderByDesc('valid_from')->get(),
            'entryTypes' => EntryType::where('is_paid', true)->where('active', true)->get(['id', 'name']),
            'categories' => VehicleCategory::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'entry_type_id' => ['required', 'exists:entry_types,id'],
            'vehicle_category_id' => ['required', 'exists:vehicle_categories,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
        ]);

        // Encerra a vigência do preço anterior da mesma combinação (histórico preservado)
        Price::where('entry_type_id', $data['entry_type_id'])
            ->where('vehicle_category_id', $data['vehicle_category_id'])
            ->whereNull('valid_to')
            ->whereDate('valid_from', '<', $data['valid_from'])
            ->update(['valid_to' => \Carbon\Carbon::parse($data['valid_from'])->subDay()->toDateString()]);

        Price::create($data);

        return back()->with('success', 'Preço cadastrado com nova vigência.');
    }

    public function update(Request $request, Price $price)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $price->update($data);

        return back()->with('success', 'Preço atualizado.');
    }

    public function destroy(Price $price)
    {
        // Histórico de preços é preservado: apenas encerra a vigência
        $price->update(['valid_to' => now()->subDay()->toDateString()]);

        return back()->with('success', 'Vigência do preço encerrada.');
    }
}
