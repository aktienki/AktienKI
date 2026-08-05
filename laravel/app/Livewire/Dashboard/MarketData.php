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

    public array $signalTransitionStats = [];

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

        $this->dailyAiScores = $indexAiScores->dailyAverages(20);
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
        $this->signalTransitionStats = $this->loadSignalTransitionStats();
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
                'signalTransitionStats' => $this->signalTransitionStats,
            ]
        );
    }

    private function loadSignalTransitionStats(): array
    {
        return Cache::remember('markets.signal-transition-stats.5d.v2', now()->addMinutes(2), function (): array {
            $history = DB::table('predictions as prediction')
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.type', 'stock')
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->where('prediction.prediction_time', '>=', now()->subDays(14))
                ->whereIn(DB::raw('UPPER(prediction.signal)'), ['SELL', 'HOLD', 'WATCH', 'BUY'])
                ->select([
                    'prediction.id', 'prediction.instrument_id',
                    'prediction.trained_model_id', 'prediction.prediction_time',
                ])
                ->selectRaw("CASE UPPER(prediction.signal)
                    WHEN 'SELL' THEN 0 WHEN 'HOLD' THEN 1
                    WHEN 'WATCH' THEN 2 WHEN 'BUY' THEN 3
                END AS signal_rank");

            $sequenced = DB::query()
                ->fromSub($history, 'signal_history')
                ->select('signal_history.*')
                ->selectRaw('LAG(signal_history.signal_rank) OVER (
                    PARTITION BY signal_history.instrument_id, COALESCE(signal_history.trained_model_id, 0)
                    ORDER BY signal_history.prediction_time, signal_history.id
                ) AS previous_rank');

            $transitions = DB::query()
                ->fromSub($sequenced, 'signal_transition')
                ->whereNotNull('previous_rank')
                ->whereColumn('previous_rank', '<>', 'signal_rank')
                ->where('prediction_time', '>=', now()->subDays(5));

            $stats = (clone $transitions)
                ->selectRaw('COUNT(*) AS transition_count')
                ->selectRaw('SUM(CASE WHEN signal_rank > previous_rank THEN 1 ELSE 0 END) AS positive_count')
                ->selectRaw('SUM(CASE WHEN signal_rank < previous_rank THEN 1 ELSE 0 END) AS negative_count')
                ->selectRaw('AVG(CASE WHEN signal_rank > previous_rank THEN 1.0 ELSE -1.0 END) AS average_direction')
                ->first();

            $matrix = (clone $transitions)
                ->groupBy('previous_rank', 'signal_rank')
                ->get([
                    'previous_rank', 'signal_rank', DB::raw('COUNT(*) AS transition_count'),
                ])
                ->mapWithKeys(fn (object $transition): array => [
                    $transition->previous_rank.'-'.$transition->signal_rank => (int) $transition->transition_count,
                ])
                ->all();

            $latestSignals = DB::table('predictions as current_prediction')
                ->join('instruments as current_instrument', 'current_instrument.id', '=', 'current_prediction.instrument_id')
                ->where('current_instrument.type', 'stock')
                ->where('current_instrument.is_active', true)
                ->whereNull('current_instrument.deleted_at')
                ->whereIn(DB::raw('UPPER(current_prediction.signal)'), ['SELL', 'HOLD', 'WATCH', 'BUY'])
                ->selectRaw('DISTINCT ON (current_prediction.instrument_id) current_prediction.instrument_id')
                ->selectRaw("UPPER(current_prediction.signal) AS signal")
                ->orderBy('current_prediction.instrument_id')
                ->orderByDesc('current_prediction.prediction_time')
                ->orderByDesc('current_prediction.id');
            $distribution = DB::query()
                ->fromSub($latestSignals, 'latest_signal')
                ->groupBy('signal')
                ->get(['signal', DB::raw('COUNT(*) AS signal_count')])
                ->mapWithKeys(fn (object $row): array => [(string) $row->signal => (int) $row->signal_count])
                ->all();
            $distribution = collect(['SELL', 'HOLD', 'WATCH', 'BUY'])
                ->mapWithKeys(fn (string $signal): array => [$signal => (int) ($distribution[$signal] ?? 0)])
                ->all();

            return [
                'transition_count' => (int) ($stats?->transition_count ?? 0),
                'positive_count' => (int) ($stats?->positive_count ?? 0),
                'negative_count' => (int) ($stats?->negative_count ?? 0),
                'average' => round((float) ($stats?->average_direction ?? 0), 2),
                'matrix' => $matrix,
                'max_count' => max([0, ...array_values($matrix)]),
                'distribution' => $distribution,
                'distribution_total' => array_sum($distribution),
                'distribution_max' => max([0, ...array_values($distribution)]),
            ];
        });
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
