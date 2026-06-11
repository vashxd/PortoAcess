<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedVehicle;
use App\Models\Company;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthorizedVehicleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Autorizados', [
            'authorized' => AuthorizedVehicle::with(['vehicle.category', 'company:id,name'])
                ->orderByDesc('active')->orderBy('employee_name')->get(),
            'companies' => Company::where('active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => VehicleCategory::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:10'],
            'vehicle_category_id' => ['nullable', 'exists:vehicle_categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:funcionario,empresa'],
            'employee_name' => ['nullable', 'required_if:type,funcionario', 'string', 'max:255'],
            'company_id' => ['nullable', 'required_if:type,empresa', 'exists:companies,id'],
            'valid_until' => ['nullable', 'date'],
        ]);

        $vehicle = Vehicle::firstOrCreate(
            ['plate' => Vehicle::normalizePlate($data['plate'])],
            [
                'vehicle_category_id' => $data['vehicle_category_id'] ?? null,
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'color' => $data['color'] ?? null,
                'owner_name' => $data['employee_name'] ?? null,
            ],
        );

        AuthorizedVehicle::create([
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'employee_name' => $data['employee_name'] ?? null,
            'company_id' => $data['type'] === 'empresa' ? $data['company_id'] : null,
            'valid_until' => $data['valid_until'] ?? null,
            'active' => true,
        ]);

        return back()->with('success', 'Veículo autorizado cadastrado.');
    }

    public function update(Request $request, AuthorizedVehicle $authorizedVehicle)
    {
        $data = $request->validate([
            'employee_name' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'valid_until' => ['nullable', 'date'],
            'active' => ['required', 'boolean'],
        ]);

        $authorizedVehicle->update($data);

        return back()->with('success', 'Autorização atualizada.');
    }

    public function destroy(AuthorizedVehicle $authorizedVehicle)
    {
        $authorizedVehicle->update(['active' => false]);

        return back()->with('success', 'Autorização revogada.');
    }
}
