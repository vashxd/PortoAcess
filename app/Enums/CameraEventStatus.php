<?php

namespace App\Enums;

enum CameraEventStatus: string
{
    case Pendente = 'pendente';
    case Vinculado = 'vinculado';
    case Descartado = 'descartado';
}
