<?php

return [
    'stocks' => [
        'default_interval' => '1d',
        'default_history_years' => 10,
    ],

    'forex' => [
        'default_interval' => '15m',
        'default_history_years' => 3,
    ],

    'ml_engine' => [
        'url' => env('ML_ENGINE_URL', 'http://127.0.0.1:8100'),
        'token' => env('ML_ENGINE_TOKEN'),
        'timeout_seconds' => 30,
    ],
];
