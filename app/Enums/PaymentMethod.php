<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case CartaoDebito = 'cartao_debito';
    case CartaoCredito = 'cartao_credito';
    case Dinheiro = 'dinheiro';
    case Faturado = 'faturado';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::CartaoDebito => 'Cartão de débito',
            self::CartaoCredito => 'Cartão de crédito',
            self::Dinheiro => 'Dinheiro',
            self::Faturado => 'Faturado (empresa)',
        };
    }
}
