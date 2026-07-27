<?php

// app/Livewire/Dashboard/MarketSentiment.php

namespace App\Livewire\Dashboard;

use App\Services\MarketService;
use App\Services\YahooFinanceService;
use App\Services\IndexAiScoreService;
use Livewire\Component;

class MarketSentiment extends Component
{
    public array $cards = [];

    protected array $symbols = [

        'DAX'        => '^GDAXI',
        'NASDAQ'     => '^IXIC',
        'S&P 500'    => '^GSPC',
        'Japan'      => '^N225',
        'China'      => '000001.SS',

    ];

    public function mount(
        YahooFinanceService $yahoo,
        MarketService $marketService,
        IndexAiScoreService $indexAiScores
    ): void
    {
        $this->load($yahoo, $marketService, $indexAiScores);
    }

    public function refreshData(
        YahooFinanceService $yahoo,
        MarketService $marketService,
        IndexAiScoreService $indexAiScores
    ): void
    {
        $this->load($yahoo, $marketService, $indexAiScores);
    }

    protected function load(
        YahooFinanceService $yahoo,
        MarketService $marketService,
        IndexAiScoreService $indexAiScores
    ): void
    {
        $markets = [];

        foreach ($this->symbols as $name => $symbol) {

            $quote = $yahoo->quote($symbol);

            $markets[] = [

                'name'     => $name,
                'price'    => $quote['price'] ?? null,
                'currency' => $quote['currency'] ?? '',
                'change'   => $quote['change_percent'] ?? null,
                'candles'  => $yahoo->candles($symbol),

            ];

        }

        $this->cards = $marketService->marketSituations(
            $markets,
            $indexAiScores->scores(),
        );
    }

    public function render()
    {
        return view(
            'livewire.dashboard.market-sentiment',
            [
                'sentiment' => $this->cards,
            ]
        );
    }
}
