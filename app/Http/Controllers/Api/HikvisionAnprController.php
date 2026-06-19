<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CameraEvent;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Adapter do push ANPR das câmeras Hikvision DS-TCG406-E(S) (caminho B).
 *
 * A câmera faz o reconhecimento de placa a bordo e envia o evento para este
 * endpoint ("HTTP Listening" / "Notificar central de vigilância" na câmera).
 * Aqui o formato proprietário da Hikvision é traduzido para o mesmo CameraEvent
 * que o CameraWebhookController já grava — então todo o resto do sistema
 * (vínculo de acesso, cor/modelo, cobrança) continua funcionando igual.
 *
 * Formato do push (varia por firmware — CONFIRMAR com um payload real da câmera):
 *   - multipart/form-data com uma parte XML (EventNotificationAlert/ANPR) + imagem(ns) JPEG; ou
 *   - corpo XML puro; ou
 *   - JSON (firmwares mais novos).
 * O parser abaixo tenta os três e busca os campos de forma tolerante.
 */
class HikvisionAnprController extends Controller
{
    public function store(Request $request)
    {
        // Autenticação simples por token (mesmo header do webhook atual).
        // A câmera Hikvision não envia headers customizados em todo firmware;
        // se não der pra configurar o header, proteja por IP/firewall (ver docs).
        $token = (string) config('portoaccess.camera_token');
        $sent = (string) ($request->header('X-Camera-Token') ?? $request->query('token', ''));
        if ($token !== '' && ! hash_equals($token, $sent)) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        $fields = $this->extractFields($request);

        if (empty($fields['plate'])) {
            Log::warning('[HIKVISION] Push ANPR sem placa reconhecida.', [
                'ip' => $request->ip(),
                'content_type' => $request->header('Content-Type'),
            ]);

            // 200 para a câmera não ficar reenfileirando; sem placa não há evento útil.
            return response()->json(['status' => 'ignored', 'reason' => 'no_plate'], 200);
        }

        $camera = $this->resolveCamera($request, $fields['source_ip'] ?? null);
        $photoPath = $this->storePhoto($request, $camera);

        $event = CameraEvent::create([
            'camera' => $camera,
            'plate' => Vehicle::normalizePlate($fields['plate']),
            'color' => $fields['color'] ?? null,
            'model' => $fields['model'] ?? null,
            'brand' => $fields['brand'] ?? null,
            'confidence' => $fields['confidence'] ?? null,
            'photo_path' => $photoPath,
            'occurred_at' => $fields['occurred_at'] ?? now(),
        ]);

        return response()->json(['id' => $event->id, 'status' => 'ok'], 201);
    }

    /**
     * Extrai placa/cor/modelo/marca/etc. do push, seja XML, multipart ou JSON.
     *
     * @return array{plate:?string,color:?string,model:?string,brand:?string,confidence:?float,occurred_at:?string,source_ip:?string}
     */
    private function extractFields(Request $request): array
    {
        // 1) JSON direto
        if ($request->isJson() && $request->json()->all()) {
            return $this->mapKeys($request->json()->all());
        }

        // 2) Localiza um trecho XML (corpo puro, campo de formulário ou arquivo enviado)
        $xml = $this->findXml($request);
        if ($xml !== null) {
            return $this->parseXml($xml);
        }

        // 3) Fallback: campos de formulário soltos
        if ($request->all()) {
            return $this->mapKeys($request->all());
        }

        return $this->emptyFields();
    }

    /** Procura uma string XML no corpo, nos campos ou nos arquivos enviados. */
    private function findXml(Request $request): ?string
    {
        $raw = $request->getContent();
        if ($this->looksLikeXml($raw)) {
            return $raw;
        }

        foreach ($request->all() as $value) {
            if (is_string($value) && $this->looksLikeXml($value)) {
                return $value;
            }
        }

        foreach ($request->allFiles() as $file) {
            $files = is_array($file) ? $file : [$file];
            foreach ($files as $f) {
                $mime = strtolower((string) $f->getClientMimeType());
                $name = strtolower((string) $f->getClientOriginalName());
                if (str_contains($mime, 'xml') || str_ends_with($name, '.xml')) {
                    $content = @file_get_contents($f->getRealPath());
                    if ($this->looksLikeXml($content)) {
                        return $content;
                    }
                }
            }
        }

        return null;
    }

    private function looksLikeXml(?string $s): bool
    {
        return is_string($s) && str_contains($s, '<') && str_contains($s, '>');
    }

    /** @return array<string,mixed> */
    private function parseXml(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);

        if ($sx === false) {
            return $this->emptyFields();
        }

        // Achata o XML em pares chave=>valor (último nível), tag em minúsculas.
        $flat = [];
        $walk = function ($node, $walk) use (&$flat) {
            foreach ($node->children() as $child) {
                if ($child->count() > 0) {
                    $walk($child, $walk);
                } else {
                    $flat[strtolower($child->getName())] = trim((string) $child);
                }
            }
        };
        $walk($sx, $walk);

        return $this->mapKeys($flat);
    }

    /**
     * Mapeia nomes de campo conhecidos (Hikvision + genéricos) para o nosso schema.
     * Os nomes Hikvision PODEM variar por firmware — ajuste aqui após inspecionar
     * um push real (ver docs/integracao-camera-hikvision.md).
     *
     * @param  array<string,mixed>  $in
     * @return array<string,mixed>
     */
    private function mapKeys(array $in): array
    {
        // normaliza chaves para minúsculas
        $d = [];
        foreach ($in as $k => $v) {
            $d[strtolower((string) $k)] = $v;
        }

        $pick = fn (array $keys) => collect($keys)->map(fn ($k) => $d[$k] ?? null)
            ->first(fn ($v) => $v !== null && $v !== '');

        return [
            'plate' => $pick(['licenseplate', 'plate', 'platenumber', 'plate_number', 'platenum']),
            'color' => $pick(['vehiclecolor', 'color', 'platecolor']),
            'model' => $pick(['vehiclemodel', 'model', 'vehicletype', 'vehicle_type']),
            'brand' => $pick(['vehiclelogo', 'brand', 'vehiclebrand', 'logo']),
            'confidence' => ($c = $pick(['confidencelevel', 'confidence', 'reliability'])) !== null ? (float) $c : null,
            'occurred_at' => $pick(['datetime', 'capturetime', 'occurred_at', 'time', 'abstime']),
            'source_ip' => $pick(['ipaddress', 'ipv4address', 'source_ip', 'deviceip']),
        ];
    }

    /** @return array<string,null> */
    private function emptyFields(): array
    {
        return [
            'plate' => null, 'color' => null, 'model' => null, 'brand' => null,
            'confidence' => null, 'occurred_at' => null, 'source_ip' => null,
        ];
    }

    /**
     * Resolve a cancela (entrada|saida): por ?camera= explícito, senão pelo IP de
     * origem comparado com config('portoaccess.cameras'); fallback 'entrada'.
     */
    private function resolveCamera(Request $request, ?string $sourceIp): string
    {
        $q = $request->query('camera');
        if (in_array($q, ['entrada', 'saida'], true)) {
            return $q;
        }

        $ip = $sourceIp ?: $request->ip();
        foreach (['entrada', 'saida'] as $cam) {
            if ($ip && config("portoaccess.cameras.{$cam}.ip") === $ip) {
                return $cam;
            }
        }

        return 'entrada';
    }

    /** Salva a primeira imagem JPEG recebida no mesmo padrão do webhook atual. */
    private function storePhoto(Request $request, string $camera): ?string
    {
        foreach ($request->allFiles() as $file) {
            $files = is_array($file) ? $file : [$file];
            foreach ($files as $f) {
                $mime = strtolower((string) $f->getClientMimeType());
                if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') || str_contains($mime, 'image')) {
                    $path = 'captures/'.now()->format('Y/m/d').'/'.uniqid($camera.'_').'.jpg';
                    Storage::disk('public')->put($path, @file_get_contents($f->getRealPath()));

                    return $path;
                }
            }
        }

        return null;
    }
}
