<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\AccessRecord;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Registra um ou mais pagamentos (misto = mais de um item).
     * Valida que a soma cobre exatamente o saldo devedor (RF06).
     *
     * @param array $payments [['method' => 'pix', 'amount' => 10.0, 'card_brand' => null], ...]
     */
    public function register(AccessRecord $record, array $payments, int $userId, ?string $billingJustification = null): void
    {
        $balance = $record->balanceDue();
        $sum = round(array_sum(array_map(fn ($p) => (float) $p['amount'], $payments)), 2);

        if (abs($sum - $balance) > 0.009) {
            throw ValidationException::withMessages([
                'payments' => sprintf(
                    'A soma das formas de pagamento (R$ %.2f) difere do valor devido (R$ %.2f).',
                    $sum, $balance,
                ),
            ]);
        }

        foreach ($payments as $p) {
            $method = PaymentMethod::from($p['method']);

            if ($method === PaymentMethod::Faturado) {
                $this->validateBilled($record, $billingJustification);
            }

            Payment::create([
                'access_record_id' => $record->id,
                'method' => $method,
                'amount' => round((float) $p['amount'], 2),
                'card_brand' => $p['card_brand'] ?? null,
                'paid_at' => now(),
                'user_id' => $userId,
                'notes' => $method === PaymentMethod::Faturado ? $billingJustification : ($p['notes'] ?? null),
            ]);
        }
    }

    /**
     * Faturado exige empresa conveniada vinculada e veículo autorizado
     * (ou justificativa manual do operador, registrada em auditoria).
     */
    private function validateBilled(AccessRecord $record, ?string $justification): void
    {
        if (! $record->company_id) {
            throw ValidationException::withMessages([
                'payments' => 'Pagamento faturado exige uma empresa conveniada vinculada ao acesso.',
            ]);
        }

        $company = $record->company;

        if (! $company->active) {
            throw ValidationException::withMessages([
                'payments' => 'A empresa conveniada está inativa.',
            ]);
        }

        $authorized = $record->vehicle->authorizations()
            ->where('type', 'empresa')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()))
            ->exists();

        if (! $authorized && ! $justification) {
            throw ValidationException::withMessages([
                'payments' => 'Veículo não autorizado pela empresa. Informe uma justificativa para faturar mesmo assim.',
            ]);
        }

        // Limite de crédito opcional da empresa
        if ($company->credit_limit !== null) {
            $pending = $company->pendingBilledTotal();
            if ($pending + $record->balanceDue() > (float) $company->credit_limit) {
                throw ValidationException::withMessages([
                    'payments' => sprintf(
                        'Limite de crédito da empresa excedido (em aberto: R$ %.2f, limite: R$ %.2f).',
                        $pending, (float) $company->credit_limit,
                    ),
                ]);
            }
        }
    }
}
