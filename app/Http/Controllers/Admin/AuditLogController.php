<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user:id,name')
            ->when($request->query('entidade'), fn ($q, $e) => $q->where('entity', $e))
            ->when($request->query('acao'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('usuario'), fn ($q, $u) => $q->where('user_id', $u))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Auditoria', [
            'logs' => $logs,
            'filtros' => [
                'entidade' => $request->query('entidade', ''),
                'acao' => $request->query('acao', ''),
            ],
            'entidades' => AuditLog::whereNotNull('entity')->distinct()->orderBy('entity')->pluck('entity'),
            'acoes' => AuditLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
