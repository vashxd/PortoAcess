<?php

namespace App\Enums;

enum ChargeMoment: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Na entrada',
            self::Saida => 'Na saída',
        };
    }
}
