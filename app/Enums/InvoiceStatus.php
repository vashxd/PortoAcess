<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Aberta = 'aberta';
    case Paga = 'paga';
    case Vencida = 'vencida';

    public function label(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Paga => 'Paga',
            self::Vencida => 'Vencida',
        };
    }
}
