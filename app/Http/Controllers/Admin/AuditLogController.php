<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        // Anexa as fotos do veículo aos logs ligados a um registro de acesso,
        // para o auditor conferir as imagens de entrada/saída na própria trilha.
        $accessIds = $logs->getCollection()
            ->where('entity', 'AccessRecord')
            ->pluck('entity_id')
            ->filter()
            ->unique();

        $photos = $accessIds->isNotEmpty()
            ? AccessRecord::whereIn('id', $accessIds)->get(['id', 'entry_photo', 'exit_photo'])->keyBy('id')
            : collect();

        $logs->through(function ($log) use ($photos) {
            $record = $log->entity === 'AccessRecord' ? $photos->get($log->entity_id) : null;
            $log->entry_photo_url = $record?->entry_photo ? Storage::url($record->entry_photo) : null;
            $log->exit_photo_url = $record?->exit_photo ? Storage::url($record->exit_photo) : null;

            return $log;
        });

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
