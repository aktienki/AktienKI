<?php

return [
    'models' => [
        'standard' => env('OPENAI_CHAT_MODEL', 'gpt-5.4-mini'),
        'deep' => env('OPENAI_CHAT_DEEP_MODEL', 'gpt-5.4'),
    ],
    'prices_usd_per_million' => [
        'gpt-5.4-mini' => ['input' => 0.75, 'cached_input' => 0.075, 'output' => 4.50],
        'gpt-5.4' => ['input' => 2.50, 'cached_input' => 0.25, 'output' => 15.00],
    ],
    'usd_to_eur' => (float) env('OPENAI_USD_TO_EUR', 0.92),
    'monthly_budget_cents' => [
        'free' => 5,
        'plus' => 50,
        'pro' => 200,
    ],
    // Five Euro are shown to Pro users. The reserve absorbs delayed usage and tool costs.
    'monthly_hard_limit_cents' => [
        'free' => 4,
        'plus' => 45,
        'pro' => 180,
    ],
    'warning_percent' => 80,
    'reservation_cents' => ['standard' => 1, 'deep' => 8],
];
