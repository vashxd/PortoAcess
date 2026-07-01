<?php

namespace App\Enums;

enum DepartureStatus: string
{
    case Agendada = 'agendada';
    case Embarcando = 'embarcando';
    case Encerrada = 'encerrada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Embarcando => 'Embarcando',
            self::Encerrada => 'Encerrada',
            self::Cancelada => 'Cancelada',
        };
    }
}
