<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Uso: ->middleware('role:admin,financeiro')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            abort(403, 'Usuário inativo ou não autenticado.');
        }

        if (! in_array($user->role->value, $roles, true)) {
            abort(403, 'Você não tem permissão para acessar esta área.');
        }

        return $next($request);
    }
}
