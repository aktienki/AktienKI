<?php

return [
    'python_engine' => [
        'path' => env('AKTIENKI_PYTHON_ENGINE_PATH', '/Users/silviotaubert/Downloads/python-engine'),
        'executable' => env('AKTIENKI_PYTHON_EXECUTABLE'),
    ],
    'default_plan' => env('AKTIENKI_DEFAULT_PLAN', 'free'),

    'signals' => [
        'buy_threshold' => (float) env('AKTIENKI_BUY_THRESHOLD', 2.0),
        'sell_threshold' => (float) env('AKTIENKI_SELL_THRESHOLD', -2.0),
    ],

    'dashboard' => [
        'top_predictions_limit' => (int) env('AKTIENKI_TOP_PREDICTIONS_LIMIT', 10),
        'watchlist_limit_free' => (int) env('AKTIENKI_WATCHLIST_LIMIT_FREE', 10),
    ],

    'twelve_data' => [
        'api_key' => env('TWELVE_DATA_API_KEY'),
        'base_url' => env('TWELVE_DATA_BASE_URL', 'https://api.twelvedata.com'),
        'indexes_enabled' => (bool) env('TWELVE_DATA_INDEXES_ENABLED', false),
    ],
];
