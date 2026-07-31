<?php

// app/Livewire/Dashboard/MarketData.php

namespace App\Livewire\Dashboard;

use App\Services\MarketService;
use App\Services\IndexAiScoreService;
use Illuminate\Support\Facades\Cache;
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
        MarketService $marketService,
        IndexAiScoreService $indexAiScores
    ): void
    {
        $this->loadData($marketService, $indexAiScores, true);
    }

    public function refreshData(
        MarketService $marketService,
        IndexAiScoreService $indexAiScores
    ): void
    {
        $this->loadData($marketService, $indexAiScores, false);
    }

    private function loadData(
        MarketService $marketService,
        IndexAiScoreService $indexAiScores,
        bool $loadCandles
    ): void
    {
        $existingCandles = collect($this->markets)
            ->mapWithKeys(fn (array $market) => [
                $market['symbol'] ?? $market['name'] => $market['candles'] ?? [],
            ]);

        $databaseMarkets = $this->databaseMarkets();
        $this->markets = collect($this->symbols)
            ->map(function (string $symbol, string $name) use ($databaseMarkets, $loadCandles, $existingCandles): array {
                $market = $databaseMarkets[$symbol] ?? [];

                return [
                    'name' => $name,
                    'symbol' => $symbol,
                    'price' => $market['price'] ?? null,
                    'currency' => $market['currency'] ?? '',
                    'change' => $market['change'] ?? null,
                    'candles' => $loadCandles
                        ? ($market['candles'] ?? [])
                        : $existingCandles->get($symbol, $market['candles'] ?? []),
                ];
            })
            ->values()
            ->all();

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
        $dailyMarketAnalysis = Cache::remember('dashboard_daily_market_analysis', now()->addMinute(), fn () =>
            DB::table('daily_market_ai_analyses')
                ->orderByDesc('analysis_date')
                ->orderByDesc('id')
                ->first());
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

    private function databaseMarkets(): array
    {
        return Cache::remember('dashboard_index_market_bars', now()->addSeconds(30), function (): array {
            $bars = DB::table('instruments as instrument')
                ->join('price_bars as bar', 'bar.instrument_id', '=', 'instrument.id')
                ->whereIn('instrument.symbol', array_values($this->symbols))
                ->whereIn('bar.interval', ['1m', '1d'])
                ->where('bar.bar_time', '>=', now()->subDays(35))
                ->orderBy('instrument.symbol')
                ->orderByDesc('bar.bar_time')
                ->get([
                    'instrument.symbol', 'instrument.currency', 'bar.interval',
                    'bar.bar_time', 'bar.open', 'bar.high', 'bar.low', 'bar.close',
                ])
                ->groupBy('symbol');

            return collect($this->symbols)->mapWithKeys(function (string $symbol) use ($bars): array {
                $symbolBars = $bars->get($symbol, collect());
                $latest = $symbolBars->first();
                $previousDaily = $symbolBars
                    ->filter(fn (object $bar): bool => $bar->interval === '1d')
                    ->values()
                    ->get(1);
                $price = $latest && is_numeric($latest->close) ? (float) $latest->close : null;
                $previous = $previousDaily && is_numeric($previousDaily->close) ? (float) $previousDaily->close : null;
                $candles = $symbolBars
                    ->filter(fn (object $bar): bool => $bar->interval === '1m')
                    ->take(48)
                    ->reverse()
                    ->values()
                    ->map(fn (object $bar): array => [
                        'x' => \Illuminate\Support\Carbon::parse($bar->bar_time)->getTimestampMs(),
                        'y' => [(float) $bar->open, (float) $bar->high, (float) $bar->low, (float) $bar->close],
                    ])
                    ->all();

                return [$symbol => [
                    'price' => $price,
                    'currency' => $latest?->currency ?? '',
                    'change' => $price !== null && $previous
                        ? (($price - $previous) / $previous) * 100
                        : null,
                    'candles' => $candles,
                ]];
            })->all();
        });
    }
}
