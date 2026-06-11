<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CancellationController extends Controller
{
    /** Solicitações de cancelamento pendentes de aprovação do admin. */
    public function index()
    {
        $requests = AccessRecord::with(['vehicle', 'entryType', 'cancelRequester'])
            ->whereNotNull('cancel_requested_at')
            ->whereNull('cancelled_at')
            ->where('status', '!=', AccessStatus::Cancelado)
            ->orderBy('cancel_requested_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'plate' => $r->vehicle->plate,
                'entry_type' => $r->entryType->name,
                'entered_at' => $r->entered_at->toIso8601String(),
                'status' => $r->status->label(),
                'amount_due' => (float) $r->amount_due,
                'paid' => $r->totalPaid(),
                'reason' => $r->cancel_request_reason,
                'requested_at' => $r->cancel_requested_at->toIso8601String(),
                'requested_by' => $r->cancelRequester?->name,
            ]);

        return Inertia::render('Admin/Cancelamentos', ['requests' => $requests]);
    }

    public function approve(Request $request, AccessRecord $record)
    {
        $record->update([
            'status' => AccessStatus::Cancelado,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);

        AuditLog::record('cancellation_approved', $record, null, [
            'reason' => $record->cancel_request_reason,
        ]);

        return back()->with('success', 'Registro cancelado (mantido no histórico para auditoria).');
    }

    public function reject(Request $request, AccessRecord $record)
    {
        $reason = $record->cancel_request_reason;

        $record->update([
            'cancel_requested_at' => null,
            'cancel_request_reason' => null,
            'cancel_requested_by' => null,
        ]);

        AuditLog::record('cancellation_rejected', $record, null, ['original_reason' => $reason]);

        return back()->with('success', 'Solicitação de cancelamento rejeitada.');
    }
}
