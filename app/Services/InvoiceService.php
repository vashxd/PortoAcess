<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * Fecha o período de uma empresa: agrupa todos os acessos faturados
     * ainda sem fatura dentro do intervalo e gera a fatura com extrato.
     */
    public function closePeriod(Company $company, CarbonInterface $start, CarbonInterface $end, ?CarbonInterface $dueDate = null): Invoice
    {
        return DB::transaction(function () use ($company, $start, $end, $dueDate) {
            $payments = Payment::where('method', PaymentMethod::Faturado)
                ->whereHas('accessRecord', fn ($q) => $q
                    ->where('company_id', $company->id)
                    ->where('status', '!=', 'cancelado')
                    ->whereBetween('entered_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]))
                ->whereDoesntHave('accessRecord.invoiceItems')
                ->with('accessRecord')
                ->get();

            if ($payments->isEmpty()) {
                throw ValidationException::withMessages([
                    'period' => 'Nenhum acesso faturado pendente no período informado para esta empresa.',
                ]);
            }

            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(),
                'company_id' => $company->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'due_date' => $dueDate?->toDateString() ?? $end->copy()->addDays(10)->toDateString(),
                'total' => $payments->sum('amount'),
                'status' => InvoiceStatus::Aberta,
            ]);

            foreach ($payments as $payment) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'access_record_id' => $payment->access_record_id,
                    'amount' => $payment->amount,
                ]);
            }

            return $invoice;
        });
    }

    /** Marca faturas abertas e vencidas como 'vencida' (rotina diária). */
    public function flagOverdue(): int
    {
        return Invoice::where('status', InvoiceStatus::Aberta)
            ->whereDate('due_date', '<', now())
            ->update(['status' => InvoiceStatus::Vencida]);
    }
}
