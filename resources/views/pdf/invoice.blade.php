<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Fatura {{ $invoice->number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #1e293b; margin: 24px; }
        h1 { font-size: 20px; margin: 0; }
        .header { border-bottom: 3px solid #0ea5e9; padding-bottom: 12px; margin-bottom: 16px; }
        .muted { color: #64748b; }
        .box { background: #f1f5f9; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #0ea5e9; color: #fff; text-align: left; padding: 6px 8px; font-size: 11px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .total td { font-weight: bold; font-size: 13px; border-top: 2px solid #0ea5e9; background: #e0f2fe; }
        .footer { margin-top: 24px; font-size: 10px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PortoAccess — Fatura {{ $invoice->number }}</h1>
        <span class="muted">Sistema de Controle de Acesso Veicular Portuário · Manaus/AM</span>
    </div>

    <div class="box">
        <strong>{{ $invoice->company->name }}</strong><br>
        @if($invoice->company->cnpj) CNPJ: {{ $invoice->company->cnpj }}<br> @endif
        @if($invoice->company->contact) Contato: {{ $invoice->company->contact }} @endif
        @if($invoice->company->email) · {{ $invoice->company->email }} @endif
    </div>

    <p>
        <strong>Período:</strong> {{ $invoice->period_start->format('d/m/Y') }} a {{ $invoice->period_end->format('d/m/Y') }} ·
        <strong>Vencimento:</strong> {{ $invoice->due_date?->format('d/m/Y') ?? '—' }} ·
        <strong>Situação:</strong> {{ $invoice->status->label() }}
        @if($invoice->paid_at) (paga em {{ $invoice->paid_at->format('d/m/Y H:i') }}) @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Placa</th>
                <th>Tipo de acesso</th>
                <th>Categoria</th>
                <th class="right">Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->accessRecord->entered_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $item->accessRecord->vehicle->plate }}</td>
                    <td>{{ $item->accessRecord->entryType->name }}</td>
                    <td>{{ $item->accessRecord->vehicleCategory->name }}</td>
                    <td class="right">{{ number_format((float) $item->amount, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">TOTAL ({{ $invoice->items->count() }} acessos)</td>
                <td class="right">R$ {{ number_format((float) $invoice->total, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }} pelo PortoAccess.
        Este documento não substitui nota fiscal.
    </div>
</body>
</html>
