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
    'portfolio_exit_backtest' => [
        // Immutable server-side adapter payload. It contains final, filtered
        // BUY transitions and both comparable exit policies for the same
        // eleven-stock universe. A checksum mismatch fails closed before an
        // existing depot simulation is reset.
        'payload_path' => env(
            'AKTIENKI_PORTFOLIO_EXIT_BACKTEST_PAYLOAD',
            base_path('../storage/depot-tests/exit-11-20260906-v1/serving-10000-max5-stock-specific-final-v3/trade-inputs.json'),
        ),
        'payload_sha256' => env(
            'AKTIENKI_PORTFOLIO_EXIT_BACKTEST_SHA256',
            'a0ca286e18fb6d8f15dd8cc11d85da637b0ebec95b444f84558f560ecd479052',
        ),
        'window' => env('AKTIENKI_PORTFOLIO_EXIT_BACKTEST_WINDOW', 'full_common'),
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

    'navigation' => [
        // The prediction tables remain reachable by their direct routes, but
        // are intentionally not advertised in the main navigation.
        'show_tables_menu' => (bool) env('AKTIENKI_SHOW_TABLES_MENU', false),
        // Account administration remains available by direct route for
        // administrators while its top-level navigation entry stays hidden.
        'show_accounts_menu' => (bool) env('AKTIENKI_SHOW_ACCOUNTS_MENU', false),
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
    'external_buy_review' => [
        // Shadow mode: the external verdict is stored and displayed but never
        // changes the ML signal, ranking or portfolio automation.
        'enabled' => (bool) env('EXTERNAL_BUY_REVIEW_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('EXTERNAL_BUY_REVIEW_V3_MODEL', 'gpt-5.6-luna'),
        'reasoning_effort' => env('EXTERNAL_BUY_REVIEW_REASONING_EFFORT', 'low'),
        'prompt_version' => env('EXTERNAL_BUY_REVIEW_V3_PROMPT_VERSION', 'buy-twelve-data-luna-v3'),
        'max_search_calls' => (int) env('EXTERNAL_BUY_REVIEW_V3_MAX_SEARCH_CALLS', 1),
        'minimum_source_domains' => (int) env('EXTERNAL_BUY_REVIEW_MIN_SOURCE_DOMAINS', 2),
        // Includes invisible reasoning tokens as well as the compact JSON.
        'max_output_tokens' => (int) env('EXTERNAL_BUY_REVIEW_V3_MAX_OUTPUT_TOKENS', 1600),
        // Pricing is snapshotted with every result so later price changes do
        // not rewrite the historical cost estimate.
        'input_price_per_million_usd' => (float) env('EXTERNAL_BUY_REVIEW_V3_INPUT_PRICE_USD', 0.2),
        'output_price_per_million_usd' => (float) env('EXTERNAL_BUY_REVIEW_V3_OUTPUT_PRICE_USD', 1.2),
        'search_price_per_call_usd' => (float) env('EXTERNAL_BUY_REVIEW_SEARCH_PRICE_USD', 0.01),
    ],
    'final_entry_shadow' => [
        // Writes only the isolated FINAL-entry decision/lifecycle tables.
        // Dashboard, emails and portfolio automation stay on their existing
        // readers until shadow parity has been explicitly verified.
        'enabled' => (bool) env('AKTIENKI_FINAL_ENTRY_SHADOW_ENABLED', false),
        'batch_limit' => (int) env('AKTIENKI_FINAL_ENTRY_SHADOW_BATCH_LIMIT', 1),
        'heartbeat_ttl_seconds' => (int) env(
            'AKTIENKI_FINAL_ENTRY_SHADOW_HEARTBEAT_TTL_SECONDS',
            86400,
        ),
        'idle_refresh_after_seconds' => (int) env(
            'AKTIENKI_FINAL_ENTRY_SHADOW_IDLE_REFRESH_SECONDS',
            21600,
        ),
    ],
    'market_data' => [
        // Analysis remains available, but historical prices for these markets
        // must neither be fetched for nor returned to public chart surfaces.
        'restricted_historical_chart_countries' => ['AU', 'JP'],
    ],
    'serving' => [
        // The screener reads production-ready predictions and model metadata
        // only from the compact pipeline-next serving database.
        'screener_enabled' => (bool) env('AKTIENKI_SERVING_SCREENER_ENABLED', true),
        // Price history never belongs in the serving database. Charts are
        // fetched on demand and retained in Laravel's filesystem cache.
        'chart_cache_hours' => (int) env('AKTIENKI_SERVING_CHART_CACHE_HOURS', 12),
        'chart_cache_store' => env('AKTIENKI_SERVING_CHART_CACHE_STORE', 'file'),
        'read_cache_store' => env('AKTIENKI_SERVING_READ_CACHE_STORE', 'file'),
    ],
];
