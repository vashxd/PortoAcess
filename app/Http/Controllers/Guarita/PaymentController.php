<?php

namespace App\Http\Controllers\Guarita;

use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function store(Request $request, AccessRecord $record)
    {
        $data = $request->validate([
            'billing_justification' => ['nullable', 'string', 'max:500'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:pix,cartao_debito,cartao_credito,dinheiro,faturado'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.card_brand' => ['nullable', 'string', 'max:30'],
        ]);

        $this->payments->register($record, $data['payments'], $request->user()->id, $data['billing_justification'] ?? null);

        return back()->with('success', 'Pagamento registrado.');
    }
}
