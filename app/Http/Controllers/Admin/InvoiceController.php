<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index()
    {
        $this->invoices->flagOverdue();

        return Inertia::render('Admin/Faturas', [
            'invoices' => Invoice::with('company:id,name')->orderByDesc('created_at')->get(),
            'companies' => Company::where('active', true)->orderBy('name')->get(['id', 'name', 'billing_cycle'])
                ->map(fn ($c) => array_merge($c->toArray(), ['pending_billed' => $c->pendingBilledTotal()])),
        ]);
    }

    /** Fechamento de período: gera a fatura com extrato de acessos. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_date' => ['nullable', 'date', 'after_or_equal:period_end'],
        ]);

        $invoice = $this->invoices->closePeriod(
            Company::findOrFail($data['company_id']),
            Carbon::parse($data['period_start']),
            Carbon::parse($data['period_end']),
            isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
        );

        return redirect()->route('admin.faturas.show', $invoice)
            ->with('success', "Fatura {$invoice->number} gerada: R$ ".number_format((float) $invoice->total, 2, ',', '.'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['company', 'items.accessRecord.vehicle', 'items.accessRecord.entryType', 'items.accessRecord.vehicleCategory']);

        return Inertia::render('Admin/FaturaDetalhe', ['invoice' => $invoice]);
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load(['company', 'items.accessRecord.vehicle', 'items.accessRecord.entryType', 'items.accessRecord.vehicleCategory']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4')
            ->download("fatura-{$invoice->number}.pdf");
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        if ($invoice->status === InvoiceStatus::Paga) {
            return back()->with('error', 'Esta fatura já está paga.');
        }

        $invoice->update([
            'status' => InvoiceStatus::Paga,
            'paid_at' => now(),
        ]);

        AuditLog::record('invoice_paid', $invoice);

        return back()->with('success', "Baixa registrada na fatura {$invoice->number}.");
    }
}
