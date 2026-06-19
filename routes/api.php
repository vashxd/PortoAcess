<?php

use App\Http\Controllers\Api\CameraWebhookController;
use App\Http\Controllers\Api\HikvisionAnprController;
use Illuminate\Support\Facades\Route;

// Endpoint que recebe os eventos HTTP push das câmeras LPR (entrada/saída).
// Usado pela ponte fast-alpr (bridge.py). Payload JSON já normalizado.
// Autenticado por token estático (CAMERA_WEBHOOK_TOKEN no .env).
Route::post('/camera/events', [CameraWebhookController::class, 'store'])->name('api.camera.events');

// Adapter do push ANPR nativo das câmeras Hikvision DS-TCG406-E(S) (caminho B).
// A câmera faz o LPR a bordo e envia XML/multipart; aqui traduzimos para CameraEvent.
// Ver docs/integracao-camera-hikvision.md.
Route::post('/camera/hikvision', [HikvisionAnprController::class, 'store'])->name('api.camera.hikvision');
