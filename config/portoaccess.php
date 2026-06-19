<?php

return [

    // Token de autenticação do webhook das câmeras LPR (header X-Camera-Token).
    'camera_token' => env('CAMERA_WEBHOOK_TOKEN', 'troque-este-token'),

    // Chave PIX estática exibida no QR Code da cobrança (fase 1).
    'pix_key' => env('PIX_KEY', ''),
    'pix_merchant_name' => env('PIX_MERCHANT_NAME', 'Porto da ponte'),
    'pix_merchant_city' => env('PIX_MERCHANT_CITY', 'MANAUS'),

    // Acionamento da cancela:
    //   'log'       -> apenas registra no log (desenvolvimento);
    //   'http'      -> POST para um módulo relé IP externo;
    //   'hikvision' -> aciona o relé embutido da câmera Hikvision via ISAPI (caminho B).
    'gate_driver' => env('GATE_DRIVER', 'log'),
    'gate_entrada_url' => env('GATE_ENTRADA_URL', ''),
    'gate_saida_url' => env('GATE_SAIDA_URL', ''),

    /*
    | Câmeras LPR Hikvision (DS-TCG406-E(S)).
    | Usadas tanto pelo driver de cancela 'hikvision' (aciona o relé via ISAPI)
    | quanto pelo adapter ANPR (HikvisionAnprController resolve qual cancela
    | gerou o evento comparando o IP de origem com o 'ip' configurado aqui).
    | 'relay_output' = ID da saída de relé na câmera (confirmar no manual/ISAPI).
    */
    'cameras' => [
        'entrada' => [
            'ip' => env('CAM_ENTRADA_IP', ''),
            'user' => env('CAM_ENTRADA_USER', 'admin'),
            'password' => env('CAM_ENTRADA_PASS', ''),
            'relay_output' => env('CAM_ENTRADA_RELAY', '1'),
        ],
        'saida' => [
            'ip' => env('CAM_SAIDA_IP', ''),
            'user' => env('CAM_SAIDA_USER', 'admin'),
            'password' => env('CAM_SAIDA_PASS', ''),
            'relay_output' => env('CAM_SAIDA_RELAY', '1'),
        ],
    ],

    // Política de retenção LGPD (RNF04)
    'photo_retention_days' => env('PHOTO_RETENTION_DAYS', 90),

    // Abrir cancela automaticamente para funcionário autorizado
    'auto_open_for_employees' => env('AUTO_OPEN_FOR_EMPLOYEES', false),

    /*
    | Consulta de situação do veículo (Sinesp Cidadão — via sidecar não oficial).
    | O Laravel NÃO fala com o Sinesp diretamente: chama um micro-serviço local
    | (alpr-bridge/sinesp_service.py) que encapsula a lib não oficial. Se o serviço
    | estiver fora do ar ou o Sinesp falhar, a consulta degrada graciosamente
    | (situacao = "indisponivel") e a guarita continua operando normalmente.
    */
    'sinesp' => [
        'enabled' => env('SINESP_ENABLED', false),
        'base_url' => env('SINESP_BASE_URL', 'http://127.0.0.1:8077'),
        'timeout' => (float) env('SINESP_TIMEOUT', 4),   // segundos — curto p/ não travar a guarita
        'cache_ttl' => (int) env('SINESP_CACHE_TTL', 600), // segundos — evita reconsultar a mesma placa
        'token' => env('SINESP_TOKEN', ''),              // header opcional X-Sinesp-Token
    ],
];
