<?php

return [

    // Token de autenticação do webhook das câmeras LPR (header X-Camera-Token).
    'camera_token' => env('CAMERA_WEBHOOK_TOKEN', 'troque-este-token'),

    // Chave PIX estática exibida no QR Code da cobrança (fase 1).
    'pix_key' => env('PIX_KEY', ''),
    'pix_merchant_name' => env('PIX_MERCHANT_NAME', 'PortoAccess'),
    'pix_merchant_city' => env('PIX_MERCHANT_CITY', 'MANAUS'),

    // Acionamento da cancela: 'log' (desenvolvimento) ou 'http' (módulo relé IP).
    'gate_driver' => env('GATE_DRIVER', 'log'),
    'gate_entrada_url' => env('GATE_ENTRADA_URL', ''),
    'gate_saida_url' => env('GATE_SAIDA_URL', ''),

    // Política de retenção LGPD (RNF04)
    'photo_retention_days' => env('PHOTO_RETENTION_DAYS', 90),

    // Abrir cancela automaticamente para funcionário autorizado
    'auto_open_for_employees' => env('AUTO_OPEN_FOR_EMPLOYEES', false),
];
