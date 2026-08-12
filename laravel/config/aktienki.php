<?php

return [
    'beta' => [
        'enabled' => (bool) env('AKTIENKI_BETA_ENABLED', true),
        'phase_ended' => (bool) env('AKTIENKI_BETA_PHASE_ENDED', false),
        'contact_email' => env('AKTIENKI_BETA_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'info@aktienki.com')),
    ],

    'saved_filter_limits' => [
        'free' => 1,
        'plus' => 3,
        'pro' => 10,
        'ultimate' => 20,
        'premium' => 20,
        'expert' => 20,
        'default' => 1,
    ],
    'python_engine' => [
        'path' => env('AKTIENKI_PYTHON_ENGINE_PATH', '/Users/silviotaubert/Downloads/python-engine'),
        'executable' => env('AKTIENKI_PYTHON_EXECUTABLE'),
        'backtests' => (bool) env('PYTHON_ENGINE_BACKTESTS', false),
    ],
    'portfolio_automation' => [
        // Keep disabled throughout the test phase. Enabling this switch starts
        // both depot changes and their transaction notifications.
        'enabled' => (bool) env('PORTFOLIO_AUTOMATION_ENABLED', false),
    ],
    'production_models' => [
        'version' => env('AKTIENKI_PRODUCTION_MODEL_VERSION', 'horizon-fusion-v1'),
        'root' => env('AKTIENKI_PRODUCTION_MODEL_ROOT', '/var/lib/aktienki/models/horizon-fusion-v1'),
        'status' => env('AKTIENKI_PRODUCTION_MODEL_STATUS', 'canary'),
    ],
    'default_plan' => env('AKTIENKI_DEFAULT_PLAN', 'free'),

    'signals' => [
        'buy_threshold' => (float) env('AKTIENKI_BUY_THRESHOLD', 2.0),
        'sell_threshold' => (float) env('AKTIENKI_SELL_THRESHOLD', -2.0),
        // Gesamtkosten für Kauf und Verkauf einschließlich angenommener
        // Slippage. Alle Signalrenditen werden nach diesem Abzug bewertet.
        'round_trip_cost_percent' => (float) env('AKTIENKI_SIGNAL_ROUND_TRIP_COST_PERCENT', 0.5),
        'minimum_net_return_percent' => (float) env('AKTIENKI_SIGNAL_MINIMUM_NET_RETURN_PERCENT', 1.0),
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
