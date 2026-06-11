<?php

use App\Http\Controllers\Api\CameraWebhookController;
use Illuminate\Support\Facades\Route;

// Endpoint que recebe os eventos HTTP push das câmeras LPR (entrada/saída).
// Autenticado por token estático (CAMERA_WEBHOOK_TOKEN no .env).
Route::post('/camera/events', [CameraWebhookController::class, 'store'])->name('api.camera.events');
