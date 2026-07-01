<?php

namespace App\Http\Controllers\Guarita;

use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use App\Models\AuditLog;
use App\Services\AccessService;
use App\Services\GateService;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    public function __construct(
        private AccessService $access,
        private GateService $gate,
    ) {}

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:10'],
            'entry_type_id' => ['required', 'exists:entry_types,id'],
            'vehicle_category_id' => ['required', 'exists:vehicle_categories,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'vessel_departure_id' => ['nullable', 'exists:vessel_departures,id'],
            'camera_event_id' => ['nullable', 'exists:camera_events,id'],
            'visitor_name' => ['nullable', 'string', 'max:255'],
            'visitor_document' => ['nullable', 'string', 'max:30'],
            'destination' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'manual_entry' => ['nullable', 'boolean'],
            'exemption_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'billing_justification' => ['nullable', 'string', 'max:500'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'in:pix,cartao_debito,cartao_credito,dinheiro,faturado'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.card_brand' => ['nullable', 'string', 'max:30'],
        ]);

        $record = $this->access->registerEntry($data, $request->user()->id);
        $this->gate->open('entrada');

        return back()->with('success', "Entrada registrada: {$record->vehicle->plate}. Cancela liberada.");
    }

    public function storeExit(Request $request, AccessRecord $record)
    {
        $data = $request->validate([
            'camera_event_id' => ['nullable', 'exists:camera_events,id'],
            'exemption_reason' => ['nullable', 'string', 'max:500'],
            'billing_justification' => ['nullable', 'string', 'max:500'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'in:pix,cartao_debito,cartao_credito,dinheiro,faturado'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.card_brand' => ['nullable', 'string', 'max:30'],
        ]);

        $record = $this->access->registerExit($record, $data, $request->user()->id);
        $this->gate->open('saida');

        return back()->with('success', "Saída registrada: {$record->vehicle->plate}. Cancela liberada.");
    }

    /** Saída sem entrada correspondente — manual e auditada (RF09). */
    public function storeExitWithoutEntry(Request $request)
    {
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:10'],
            'entry_type_id' => ['required', 'exists:entry_types,id'],
            'vehicle_category_id' => ['required', 'exists:vehicle_categories,id'],
            'camera_event_id' => ['nullable', 'exists:camera_events,id'],
            'justification' => ['required', 'string', 'max:500'],
        ]);

        $record = $this->access->registerExitWithoutEntry($data, $request->user()->id);
        $this->gate->open('saida');

        AuditLog::record('exit_without_entry', $record, null, ['justification' => $data['justification']]);

        return back()->with('success', 'Saída sem entrada registrada com justificativa.');
    }

    /** Operador não cancela: solicita e o Administrador aprova. */
    public function requestCancel(Request $request, AccessRecord $record)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $record->update([
            'cancel_requested_at' => now(),
            'cancel_request_reason' => $data['reason'],
            'cancel_requested_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Solicitação de cancelamento enviada ao administrador.');
    }
}
