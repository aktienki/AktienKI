<?php

return [
    'default_plan' => env('AKTIENKI_DEFAULT_PLAN', 'free'),

    'signals' => [
        'buy_threshold' => (float) env('AKTIENKI_BUY_THRESHOLD', 2.0),
        'sell_threshold' => (float) env('AKTIENKI_SELL_THRESHOLD', -2.0),
    ],

    'dashboard' => [
        'top_predictions_limit' => (int) env('AKTIENKI_TOP_PREDICTIONS_LIMIT', 10),
        'watchlist_limit_free' => (int) env('AKTIENKI_WATCHLIST_LIMIT_FREE', 10),
    ],
];
