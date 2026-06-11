<?php

namespace App\Enums;

enum UserRole: string
{
    case Operador = 'operador';
    case Admin = 'admin';
    case Financeiro = 'financeiro';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Operador => 'Operador (Segurança)',
            self::Admin => 'Administrador',
            self::Financeiro => 'Financeiro',
            self::Auditor => 'Auditor',
        };
    }
}
