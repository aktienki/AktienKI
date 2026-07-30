<?php

// app/Livewire/Dashboard/MarketData.php

namespace App\Livewire\Dashboard;

use App\Services\MarketService;
use App\Services\YahooFinanceService;
use App\Services\IndexAiScoreService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MarketData extends Component
{
    public array $markets = [];

    public array $sentiment = [];

    public array $dailyAiScores = [];

    public array $overallAssessment = [];

    public array $countryAiScores = [];

    public ?string $marketComment = null;

    public array $marketAnalysis = [];

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
        $dailyMarketAnalysis = DB::table('daily_market_ai_analyses')
            ->orderByDesc('analysis_date')
            ->orderByDesc('id')
            ->first();
        $this->marketComment = $dailyMarketAnalysis?->executive_summary;
        $this->marketAnalysis = $dailyMarketAnalysis ? [
            'date' => $dailyMarketAnalysis->analysis_date,
            'model' => $dailyMarketAnalysis->model,
            'outlook' => $dailyMarketAnalysis->market_outlook,
            'confidence' => (int) $dailyMarketAnalysis->confidence,
            'riskLevel' => $dailyMarketAnalysis->risk_level,
            'headline' => $dailyMarketAnalysis->headline,
            'summary' => $dailyMarketAnalysis->executive_summary,
            'breadth' => $dailyMarketAnalysis->breadth_analysis,
            'sectors' => $this->decodeJson($dailyMarketAnalysis->sector_analysis),
            'opportunities' => $this->decodeJson($dailyMarketAnalysis->opportunities),
            'risks' => $this->decodeJson($dailyMarketAnalysis->risks),
            'watchlist' => $this->decodeJson($dailyMarketAnalysis->watchlist),
        ] : [];

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
                'marketComment' => $this->marketComment,
                'marketAnalysis' => $this->marketAnalysis,
            ]
        );
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
