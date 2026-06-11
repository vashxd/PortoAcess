<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CameraEvent;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CameraWebhookController extends Controller
{
    /**
     * Recebe o push HTTP da câmera LPR (RF01).
     * Header obrigatório: X-Camera-Token (CAMERA_WEBHOOK_TOKEN no .env).
     * Foto opcional em base64 no campo "photo_base64".
     */
    public function store(Request $request)
    {
        if (! hash_equals((string) config('portoaccess.camera_token'), (string) $request->header('X-Camera-Token'))) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        $data = $request->validate([
            'camera' => ['required', 'in:entrada,saida'],
            'plate' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
            'photo_base64' => ['nullable', 'string'],
        ]);

        $photoPath = null;
        if (! empty($data['photo_base64'])) {
            $binary = base64_decode($data['photo_base64'], true);
            if ($binary !== false) {
                $photoPath = 'captures/'.now()->format('Y/m/d').'/'.uniqid($data['camera'].'_').'.jpg';
                Storage::disk('public')->put($photoPath, $binary);
            }
        }

        $event = CameraEvent::create([
            'camera' => $data['camera'],
            'plate' => $data['plate'] ? Vehicle::normalizePlate($data['plate']) : null,
            'color' => $data['color'] ?? null,
            'model' => $data['model'] ?? null,
            'brand' => $data['brand'] ?? null,
            'confidence' => $data['confidence'] ?? null,
            'photo_path' => $photoPath,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        return response()->json(['id' => $event->id, 'status' => 'ok'], 201);
    }
}
