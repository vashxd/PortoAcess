<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::withCount('authorizedVehicles')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => array_merge($c->toArray(), [
                'pending_billed' => $c->pendingBilledTotal(),
            ]));

        return Inertia::render('Admin/Empresas', [
            'companies' => $companies,
        ]);
    }

    public function show(Company $company)
    {
        $company->load(['authorizedVehicles.vehicle', 'invoices' => fn ($q) => $q->orderByDesc('period_end')]);

        return Inertia::render('Admin/EmpresaDetalhe', [
            'company' => $company,
            'pendingBilled' => $company->pendingBilledTotal(),
        ]);
    }

    public function store(Request $request)
    {
        Company::create($this->validated($request) + ['active' => true]);

        return back()->with('success', 'Empresa cadastrada.');
    }

    public function update(Request $request, Company $company)
    {
        $company->update($this->validated($request, true));

        return back()->with('success', 'Empresa atualizada.');
    }

    private function validated(Request $request, bool $withActive = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'billing_cycle' => ['required', 'in:semanal,quinzenal,mensal'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];

        if ($withActive) {
            $rules['active'] = ['required', 'boolean'];
        }

        return $request->validate($rules);
    }
}
