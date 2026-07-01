<?php

namespace App\Enums;

enum VesselType: string
{
    case Balsa = 'balsa';
    case Lancha = 'lancha';
    case Rebocador = 'rebocador';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Balsa => 'Balsa',
            self::Lancha => 'Lancha / Voadeira',
            self::Rebocador => 'Rebocador / Empurrador',
            self::Outro => 'Outra embarcação',
        };
    }

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
