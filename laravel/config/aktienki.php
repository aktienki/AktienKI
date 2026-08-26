<?php

return [
    'training_activation_quality_gate' => [
        'minimum_direction_accuracy' => (float) env('TRAINING_GATE_MIN_DIRECTION_ACCURACY', 0.55),
        'minimum_profit_factor' => (float) env('TRAINING_GATE_MIN_PROFIT_FACTOR', 1.30),
        'minimum_trade_count' => (int) env('TRAINING_GATE_MIN_TRADE_COUNT', 15),
        'reduced_minimum_trade_count' => (int) env('TRAINING_GATE_REDUCED_MIN_TRADE_COUNT', 10),
        'reduced_trade_count_minimum_direction_accuracy' => (float) env('TRAINING_GATE_REDUCED_MIN_DIRECTION_ACCURACY', 0.65),
        'maximum_drawdown' => (float) env('TRAINING_GATE_MAX_DRAWDOWN', 0.40),
    ],
    'training_report_email' => env('AKTIENKI_TRAINING_REPORT_EMAIL'),
    'beta' => [
        'enabled' => (bool) env('AKTIENKI_BETA_ENABLED', true),
        'phase_ended' => (bool) env('AKTIENKI_BETA_PHASE_ENDED', false),
        'contact_email' => env('AKTIENKI_BETA_CONTACT_EMAIL', 'admin@aktienki.com'),
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
        'server_predictions_enabled' => (bool) env('AKTIENKI_SERVER_PREDICTIONS_ENABLED', false),
        'prediction_limit' => (int) env('AKTIENKI_SERVER_PREDICTION_LIMIT', 5000),
        'prediction_timeout_seconds' => (int) env('AKTIENKI_SERVER_PREDICTION_TIMEOUT', 7200),
        'sector_filter_artifact' => env(
            'AKTIENKI_SECTOR_FILTER_ARTIFACT',
            '/var/lib/aktienki/models/current/experiments/sector_deep_learning/sector_gru_latest.npz',
        ),
        'sector_filter_report' => env(
            'AKTIENKI_SECTOR_FILTER_REPORT',
            '/var/lib/aktienki/models/current/experiments/sector_deep_learning/sector_gru_latest.json',
        ),
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
    'news' => [
        'initial_lookback_days' => (int) env('AKTIENKI_NEWS_INITIAL_LOOKBACK_DAYS', 7),
        // Venture currently allows 610 credits/minute. 250 ms keeps this job
        // near 240 requests/minute and leaves capacity for price operations.
        'twelve_data_request_delay_ms' => (int) env('AKTIENKI_NEWS_REQUEST_DELAY_MS', 250),
        'openai_model' => env('OPENAI_NEWS_MODEL', 'gpt-5.4-mini'),
        'openai_batch_size' => (int) env('AKTIENKI_NEWS_OPENAI_BATCH_SIZE', 10),
        'max_body_characters' => (int) env('AKTIENKI_NEWS_MAX_BODY_CHARACTERS', 6000),
    ],
    'market_data' => [
        // Analysis remains available, but historical prices for these markets
        // must neither be fetched for nor returned to public chart surfaces.
        'restricted_historical_chart_countries' => ['AU', 'JP'],
    ],
];
