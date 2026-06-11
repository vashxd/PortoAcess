<?php

namespace App\Enums;

enum AccessStatus: string
{
    case NoPatio = 'no_patio';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::NoPatio => 'No pátio',
            self::Finalizado => 'Finalizado',
            self::Cancelado => 'Cancelado',
        };
    }
}
