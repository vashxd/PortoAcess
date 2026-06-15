<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta a situação de um veículo no Sinesp Cidadão através de um
 * micro-serviço local (sidecar) que encapsula a lib não oficial.
 *
 * Contrato de retorno (sempre normalizado, nunca lança exceção):
 *   [
 *     'disponivel'   => bool,    // false quando o Sinesp/sidecar não respondeu
 *     'situacao'     => string,  // regular | roubo_furto | restricao | nao_encontrado | indisponivel
 *     'roubo_furto'  => bool,    // atalho p/ o alerta da guarita
 *     'mensagem'     => string,
 *     'placa'        => string,
 *     'marca'        => ?string,
 *     'modelo'       => ?string,
 *     'cor'          => ?string,
 *     'ano'          => ?string,
 *     'ano_modelo'   => ?string,
 *     'uf'           => ?string,
 *     'municipio'    => ?string,
 *     'consultado_em'=> string,  // ISO-8601
 *   ]
 */
class SinespService
{
    public function consultar(string $plate): ?array
    {
        if (! config('portoaccess.sinesp.enabled')) {
            return null; // recurso desligado — o front simplesmente não exibe nada
        }

        $plate = Vehicle::normalizePlate($plate);

        if (strlen($plate) < 7) {
            return null;
        }

        $ttl = (int) config('portoaccess.sinesp.cache_ttl', 600);

        // Cache evita reconsultar a mesma placa a cada @blur do operador.
        return Cache::remember("sinesp:{$plate}", $ttl, function () use ($plate) {
            $result = $this->fetch($plate);

            // Registra na trilha de auditoria quando há alerta de roubo/furto
            // (apenas em consulta nova — o cache impede duplicações no TTL).
            if ($result['roubo_furto'] ?? false) {
                AuditLog::record('sinesp.alerta_roubo', null, null, [
                    'placa' => $plate,
                    'situacao' => $result['situacao'],
                    'mensagem' => $result['mensagem'],
                ]);
            }

            return $result;
        });
    }

    private function fetch(string $plate): array
    {
        $base = rtrim((string) config('portoaccess.sinesp.base_url'), '/');
        $timeout = (float) config('portoaccess.sinesp.timeout', 4);
        $token = (string) config('portoaccess.sinesp.token');

        try {
            $request = Http::timeout($timeout)->acceptJson();

            if ($token !== '') {
                $request = $request->withHeaders(['X-Sinesp-Token' => $token]);
            }

            $response = $request->get("{$base}/consulta", ['placa' => $plate]);

            if (! $response->successful()) {
                return $this->indisponivel($plate, "Sinesp respondeu HTTP {$response->status()}.");
            }

            return $this->normalize($plate, $response->json() ?? []);
        } catch (\Throwable $e) {
            Log::warning('Sinesp indisponível', ['placa' => $plate, 'erro' => $e->getMessage()]);

            return $this->indisponivel($plate, 'Serviço de consulta indisponível no momento.');
        }
    }

    /** Normaliza a resposta do sidecar para o contrato fixo acima. */
    private function normalize(string $plate, array $data): array
    {
        $situacao = strtolower((string) ($data['situacao'] ?? 'indisponivel'));
        $rouboFurto = (bool) ($data['roubo_furto'] ?? in_array($situacao, ['roubo_furto', 'roubo', 'furto'], true));

        if ($rouboFurto) {
            $situacao = 'roubo_furto';
        }

        return [
            'disponivel' => (bool) ($data['disponivel'] ?? true),
            'situacao' => $situacao,
            'roubo_furto' => $rouboFurto,
            'mensagem' => (string) ($data['mensagem'] ?? ''),
            'placa' => $plate,
            'marca' => $data['marca'] ?? null,
            'modelo' => $data['modelo'] ?? null,
            'cor' => $data['cor'] ?? null,
            'ano' => isset($data['ano']) ? (string) $data['ano'] : null,
            'ano_modelo' => isset($data['ano_modelo']) ? (string) $data['ano_modelo'] : null,
            'uf' => $data['uf'] ?? null,
            'municipio' => $data['municipio'] ?? null,
            'consultado_em' => now()->toIso8601String(),
        ];
    }

    private function indisponivel(string $plate, string $mensagem): array
    {
        return [
            'disponivel' => false,
            'situacao' => 'indisponivel',
            'roubo_furto' => false,
            'mensagem' => $mensagem,
            'placa' => $plate,
            'marca' => null,
            'modelo' => null,
            'cor' => null,
            'ano' => null,
            'ano_modelo' => null,
            'uf' => null,
            'municipio' => null,
            'consultado_em' => now()->toIso8601String(),
        ];
    }
}
