<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GateService
{
    /**
     * Aciona a cancela (entrada|saida). Em desenvolvimento usa driver 'log';
     * em produção, 'http' chama o módulo relé IP configurado no .env.
     */
    public function open(string $camera): bool
    {
        $ok = match (config('portoaccess.gate_driver')) {
            'http' => $this->openViaHttp($camera),
            default => $this->openViaLog($camera),
        };

        AuditLog::record($ok ? 'gate_open' : 'gate_open_failed', null, null, ['camera' => $camera]);

        return $ok;
    }

    private function openViaLog(string $camera): bool
    {
        Log::info("[CANCELA] Abertura acionada: {$camera}");

        return true;
    }

    private function openViaHttp(string $camera): bool
    {
        $url = config("portoaccess.gate_{$camera}_url");

        if (! $url) {
            Log::warning("[CANCELA] URL do relé não configurada para {$camera}");

            return false;
        }

        try {
            return Http::timeout(3)->post($url)->successful();
        } catch (\Throwable $e) {
            Log::error("[CANCELA] Falha ao acionar relé {$camera}: {$e->getMessage()}");

            return false;
        }
    }
}
