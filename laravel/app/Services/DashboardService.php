<?php

// app/Services/DashboardService.php

namespace App\Services;

class DashboardService
{
    public function dashboard(): array
    {
        return [

            'markets' => app(MarketData::class),

            'sentiment' => app(MarketSentiment::class),

            'engine' => app(AiEngine::class),

            'signals' => app(TopSignals::class),

        ];
    }
}