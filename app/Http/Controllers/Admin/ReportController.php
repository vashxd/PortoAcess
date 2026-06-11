<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$type, $from, $to] = $this->filters($request);

        return Inertia::render('Admin/Relatorios', [
            'tipo' => $type,
            'de' => $from->toDateString(),
            'ate' => $to->toDateString(),
            'rows' => $this->rows($type, $from, $to),
        ]);
    }

    /** Exportação CSV (abre no Excel) — RF12. */
    public function export(Request $request): StreamedResponse
    {
        [$type, $from, $to] = $this->filters($request);
        $rows = $this->rows($type, $from, $to);
        $filename = "relatorio-{$type}-{$from->toDateString()}-a-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM p/ Excel reconhecer UTF-8
            if ($rows !== []) {
                fputcsv($out, array_keys($rows[0]), ';');
                foreach ($rows as $row) {
                    fputcsv($out, $row, ';');
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $type = $request->query('tipo', 'movimento');
        $from = Carbon::parse($request->query('de', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('ate', now()->toDateString()))->endOfDay();

        return [$type, $from, $to];
    }

    private function rows(string $type, Carbon $from, Carbon $to): array
    {
        return match ($type) {
            'receita' => $this->revenueByMethod($from, $to),
            'empresas' => $this->byCompany($from, $to),
            'manuais' => $this->manualAndCancelled($from, $to),
            'permanencia' => $this->stayAverages($from, $to),
            'isencoes' => $this->exemptions($from, $to),
            default => $this->movement($from, $to),
        };
    }

    /** Movimento diário de acessos. */
    private function movement(Carbon $from, Carbon $to): array
    {
        return AccessRecord::with(['vehicle', 'entryType', 'vehicleCategory', 'company', 'operatorIn', 'operatorOut'])
            ->whereBetween('entered_at', [$from, $to])
            ->orderBy('entered_at')
            ->get()
            ->map(fn ($r) => [
                'Placa' => $r->vehicle->plate,
                'Categoria' => $r->vehicleCategory->name,
                'Tipo' => $r->entryType->name,
                'Empresa' => $r->company?->name ?? '',
                'Entrada' => $r->entered_at->format('d/m/Y H:i'),
                'Saída' => $r->exited_at?->format('d/m/Y H:i') ?? '',
                'Permanência (min)' => $r->stayMinutes() ?? '',
                'Valor (R$)' => number_format((float) $r->amount_due, 2, ',', '.'),
                'Desconto (R$)' => number_format((float) $r->discount_amount, 2, ',', '.'),
                'Pago (R$)' => number_format($r->totalPaid(), 2, ',', '.'),
                'Status' => $r->status->label(),
                'Manual' => $r->manual_entry ? 'Sim' : 'Não',
                'Operador entrada' => $r->operatorIn?->name ?? '',
                'Operador saída' => $r->operatorOut?->name ?? '',
            ])->toArray();
    }

    /** Receita por forma de pagamento. */
    private function revenueByMethod(Carbon $from, Carbon $to): array
    {
        return Payment::whereBetween('paid_at', [$from, $to])
            ->get()
            ->groupBy(fn ($p) => $p->method->value)
            ->map(fn ($group, $method) => [
                'Forma de pagamento' => PaymentMethod::from($method)->label(),
                'Transações' => $group->count(),
                'Total (R$)' => number_format((float) $group->sum('amount'), 2, ',', '.'),
            ])->values()->toArray();
    }

    /** Acessos por empresa conveniada. */
    private function byCompany(Carbon $from, Carbon $to): array
    {
        return AccessRecord::with(['company', 'payments'])
            ->whereNotNull('company_id')
            ->whereBetween('entered_at', [$from, $to])
            ->where('status', '!=', AccessStatus::Cancelado)
            ->get()
            ->groupBy('company_id')
            ->map(fn ($group) => [
                'Empresa' => $group->first()->company->name,
                'Acessos' => $group->count(),
                'Total faturado (R$)' => number_format(
                    (float) $group->flatMap->payments->where('method', PaymentMethod::Faturado)->sum('amount'),
                    2, ',', '.',
                ),
            ])->values()->toArray();
    }

    /** Registros manuais e cancelados. */
    private function manualAndCancelled(Carbon $from, Carbon $to): array
    {
        return AccessRecord::with(['vehicle', 'entryType', 'operatorIn', 'operatorOut'])
            ->whereBetween('entered_at', [$from, $to])
            ->where(fn ($q) => $q->where('manual_entry', true)
                ->orWhere('status', AccessStatus::Cancelado)
                ->orWhere('exit_without_entry', true))
            ->orderBy('entered_at')
            ->get()
            ->map(fn ($r) => [
                'Placa' => $r->vehicle->plate,
                'Tipo' => $r->entryType->name,
                'Entrada' => $r->entered_at->format('d/m/Y H:i'),
                'Manual' => $r->manual_entry ? 'Sim' : 'Não',
                'Saída sem entrada' => $r->exit_without_entry ? 'Sim' : 'Não',
                'Status' => $r->status->label(),
                'Justificativa' => $r->notes ?? $r->cancel_request_reason ?? '',
                'Operador' => $r->operatorIn?->name ?? $r->operatorOut?->name ?? '',
            ])->toArray();
    }

    /** Permanência média por tipo de entrada. */
    private function stayAverages(Carbon $from, Carbon $to): array
    {
        return AccessRecord::with('entryType')
            ->whereBetween('entered_at', [$from, $to])
            ->whereNotNull('exited_at')
            ->where('status', AccessStatus::Finalizado)
            ->get()
            ->groupBy('entry_type_id')
            ->map(fn ($group) => [
                'Tipo de entrada' => $group->first()->entryType->name,
                'Saídas no período' => $group->count(),
                'Permanência média (min)' => (int) round($group->avg(fn ($r) => $r->stayMinutes())),
            ])->values()->toArray();
    }

    /** Isenções concedidas (controle de fraude interna). */
    private function exemptions(Carbon $from, Carbon $to): array
    {
        return AccessRecord::with(['vehicle', 'entryType', 'operatorIn'])
            ->whereBetween('entered_at', [$from, $to])
            ->whereNotNull('exemption_reason')
            ->orderBy('entered_at')
            ->get()
            ->map(fn ($r) => [
                'Placa' => $r->vehicle->plate,
                'Tipo' => $r->entryType->name,
                'Entrada' => $r->entered_at->format('d/m/Y H:i'),
                'Valor isento (R$)' => number_format((float) $r->discount_amount, 2, ',', '.'),
                'Justificativa' => $r->exemption_reason,
                'Operador' => $r->operatorIn?->name ?? '',
            ])->toArray();
    }
}
