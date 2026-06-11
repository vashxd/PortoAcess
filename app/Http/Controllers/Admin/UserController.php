<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Usuarios', [
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'active']),
            'roles' => collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        User::create($data + ['active' => true]);

        return back()->with('success', 'Usuário criado.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'active' => ['required', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($user->id === $request->user()->id && (! $data['active'] || $data['role'] !== UserRole::Admin->value)) {
            return back()->with('error', 'Você não pode rebaixar ou inativar o próprio usuário.');
        }

        $user->update($data);

        return back()->with('success', 'Usuário atualizado.');
    }
}
