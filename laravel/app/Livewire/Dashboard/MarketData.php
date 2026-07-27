<?php

// app/Livewire/Dashboard/MarketData.php

namespace App\Livewire\Dashboard;

use App\Services\MarketService;
use App\Services\YahooFinanceService;
use App\Services\IndexAiScoreService;
use Livewire\Component;

class MarketData extends Component
{
    public array $markets = [];

    public array $sentiment = [];

    public array $dailyAiScores = [];

    public array $overallAssessment = [];

    public array $countryAiScores = [];

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
        $this->loadData($yahoo, $marketService, $indexAiScores, true);
    }

    public function refreshData(
        YahooFinanceService $yahoo,
        MarketService $marketService,
        IndexAiScoreService $indexAiScores
    ): void
    {
        $this->loadData($yahoo, $marketService, $indexAiScores, false);
    }

    private function loadData(
        YahooFinanceService $yahoo,
        MarketService $marketService,
        IndexAiScoreService $indexAiScores,
        bool $loadCandles
    ): void
    {
        $existingCandles = collect($this->markets)
            ->mapWithKeys(fn (array $market) => [
                $market['symbol'] ?? $market['name'] => $market['candles'] ?? [],
            ]);

        $this->markets = [];

        foreach ($this->symbols as $name => $symbol) {

            $quote = $yahoo->quote($symbol);

            $this->markets[] = [

                'name'       => $name,
                'symbol'     => $symbol,
                'price'      => $quote['price'] ?? null,
                'currency'   => $quote['currency'] ?? '',
                'change'     => $quote['change_percent'] ?? null,
                'candles'    => $loadCandles
                    ? $yahoo->candles($symbol)
                    : $existingCandles->get($symbol, []),

            ];

        }

        $situations = collect($marketService->marketSituations(
            $this->markets,
            $indexAiScores->scores(),
        ))->keyBy('title');

        $this->markets = collect($this->markets)
            ->map(fn (array $market) => array_merge(
                $market,
                $situations->get($market['name'], []),
            ))
            ->all();

        $this->dailyAiScores = $indexAiScores->dailyAverages();
        $this->countryAiScores = $indexAiScores->countryScores();

        $riskLevel = data_get(auth()->user()?->meta, 'risk_profile.level', 'normal');
        $this->overallAssessment = $marketService->overallAssessment(
            $this->markets,
            $this->dailyAiScores,
            (string) $riskLevel,
        );

        $this->sentiment = $marketService->sentiment($this->markets);
    }

    public function render()
    {
        return view(
            'livewire.dashboard.market-data',
            [
                'markets' => $this->markets,
                'sentiment' => $this->sentiment,
                'dailyAiScores' => $this->dailyAiScores,
                'overallAssessment' => $this->overallAssessment,
                'countryAiScores' => $this->countryAiScores,
            ]
        );
    }
}
