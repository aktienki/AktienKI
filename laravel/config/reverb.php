<?php

return [
    'default' => env('REVERB_SERVER', 'reverb'),
    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options' => ['tls' => []],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => ['enabled' => false],
            'pulse_ingest_interval' => 15,
            'telescope_ingest_interval' => 15,
        ],
    ],
    'apps' => [
        'provider' => 'config',
        'apps' => [[
            'key' => env('REVERB_APP_KEY', 'aktienki'),
            'secret' => env('REVERB_APP_SECRET', env('APP_KEY')),
            'app_id' => env('REVERB_APP_ID', 'aktienki'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'allowed_origins' => array_values(array_unique(array_filter([
                env('APP_URL', 'http://localhost:8000'),
                'http://localhost',
                'http://localhost:8000',
                'http://127.0.0.1',
                'http://127.0.0.1:8000',
            ]))),
            'ping_interval' => 60,
            'activity_timeout' => 30,
            'max_connections' => null,
            'max_message_size' => 10_000,
        ]],
    ],
];
