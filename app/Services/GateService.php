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
            'hikvision' => $this->openViaHikvision($camera),
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

    /**
     * Aciona o relé de saída embutido na câmera Hikvision (DS-TCG406-E(S)) via ISAPI.
     * A câmera é o próprio atuador da cancela — dispensa módulo relé externo.
     *
     * Endpoint padrão Hikvision (confirmar a porta/ID no manual da câmera física):
     *   PUT http://IP/ISAPI/System/IO/outputs/<output>/trigger
     *   body: <IOPortData><outputState>high</outputState></IOPortData>
     * Autenticação: HTTP Digest (padrão das câmeras Hikvision).
     */
    private function openViaHikvision(string $camera): bool
    {
        $cam = config("portoaccess.cameras.{$camera}");

        if (empty($cam['ip'])) {
            Log::warning("[CANCELA] IP da câmera Hikvision não configurado para {$camera}");

            return false;
        }

        $output = $cam['relay_output'] ?? '1';
        $url = "http://{$cam['ip']}/ISAPI/System/IO/outputs/{$output}/trigger";
        $body = '<IOPortData><outputState>high</outputState></IOPortData>';

        try {
            $resp = Http::withDigestAuth($cam['user'] ?? 'admin', $cam['password'] ?? '')
                ->timeout(3)
                ->withBody($body, 'application/xml')
                ->put($url);

            if (! $resp->successful()) {
                Log::error("[CANCELA] Relé Hikvision {$camera} respondeu {$resp->status()}: ".substr($resp->body(), 0, 200));
            }

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::error("[CANCELA] Falha ao acionar relé Hikvision {$camera}: {$e->getMessage()}");

            return false;
        }
    }
}
