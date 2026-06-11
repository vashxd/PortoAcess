<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehicleCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Categorias', [
            'categories' => VehicleCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:vehicle_categories,name'],
        ]);

        VehicleCategory::create($data + ['active' => true]);

        return back()->with('success', 'Categoria criada.');
    }

    public function update(Request $request, VehicleCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:vehicle_categories,name,'.$category->id],
            'active' => ['required', 'boolean'],
        ]);

        $category->update($data);

        return back()->with('success', 'Categoria atualizada.');
    }

    public function destroy(VehicleCategory $category)
    {
        // Soft policy: categorias em uso são apenas inativadas (RNF06)
        if ($category->prices()->exists() || $category->id && \App\Models\AccessRecord::where('vehicle_category_id', $category->id)->exists()) {
            $category->update(['active' => false]);

            return back()->with('success', 'Categoria em uso — foi inativada em vez de excluída.');
        }

        $category->delete();

        return back()->with('success', 'Categoria excluída.');
    }
}
