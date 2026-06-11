<?php

namespace App\Http\Controllers\Guarita;

use App\Http\Controllers\Controller;
use App\Services\GateService;

class GateController extends Controller
{
    /** Abertura manual da cancela pelo operador (RF10). */
    public function open(string $camera, GateService $gate)
    {
        $ok = $gate->open($camera);

        return $ok
            ? back()->with('success', "Cancela de {$camera} acionada.")
            : back()->with('error', "Falha ao acionar a cancela de {$camera}. Verifique o módulo relé.");
    }
}
