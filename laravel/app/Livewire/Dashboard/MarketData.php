<?php

// app/Livewire/Dashboard/MarketData.php

namespace App\Livewire\Dashboard;

use App\Enums\PlanLevel;
use App\Services\FreeRegionalStockUniverseService;
use App\Services\MarketService;
use App\Services\IndexAiScoreService;
use App\Services\PlanAccessService;
use Carbon\Carbon;
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

    public array $macroCards = [];

    public array $monthlyBacktestAiScores = [];

    public bool $isRegionalFreeView = false;

    public string $regionalCountry = '';

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
        $user = auth()->user();
        $this->isRegionalFreeView = $user !== null
            && app(PlanAccessService::class)->level($user) === PlanLevel::Free;
        $regionalUniverseIds = collect();
        if ($this->isRegionalFreeView && $user !== null) {
            $universe = app(FreeRegionalStockUniverseService::class);
            $regionalUniverseIds = $universe->instrumentIds($user);
            $this->regionalCountry = $universe->country($user);
        }

        $ruleBasedAnalysis = $this->loadRuleBasedMarketAnalysis($regionalUniverseIds->all());
        $this->marketAnalysis = $this->isRegionalFreeView
            ? $ruleBasedAnalysis
            : ($this->loadExternalMarketAnalysis($ruleBasedAnalysis) ?? $ruleBasedAnalysis);
        $this->marketComment = $this->marketAnalysis['summary'] ?? null;

        $this->sentiment = $marketService->sentiment($this->markets);
        $this->signalTransitionStats = $this->loadSignalTransitionStats();
        $this->macroCards = $this->loadMacroCards();
        $this->monthlyBacktestAiScores = $this->loadMonthlyBacktestAiScores();
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
                'macroCards' => $this->macroCards,
                'monthlyBacktestAiScores' => $this->monthlyBacktestAiScores,
                'isRegionalFreeView' => $this->isRegionalFreeView,
                'regionalCountry' => $this->regionalCountry,
            ]
        );
    }

    public function loadMonthlyBacktestAiScores(): array
    {
        return Cache::remember('dashboard.monthly-backtest-ai-scores.v3', now()->addMinutes(30), function (): array {
            // Three complete calendar years: the current year plus the two
            // preceding years. Future months of the current year remain empty
            // slots in the grouped boxplot.
            $firstMonth = now()->startOfYear()->subYears(2);
            $lastDay = now()->endOfYear();

            // One observation per instrument and signal date prevents repeated
            // completed backtest runs from giving the same stock extra weight.
            $instrumentDays = DB::table('backtest_trades as trade')
                ->join('backtest_runs as run', 'run.id', '=', 'trade.backtest_run_id')
                ->whereIn('run.status', ['completed', 'completed_with_errors'])
                ->whereBetween('trade.entry_date', [$firstMonth->toDateString(), $lastDay->toDateString()])
                ->whereNotNull('trade.ki_score')
                ->whereBetween('trade.ki_score', [0, 10])
                ->groupBy('trade.instrument_id', 'trade.entry_date')
                ->selectRaw('trade.instrument_id, trade.entry_date, AVG(trade.ki_score) AS score');

            $monthly = DB::query()
                ->fromSub($instrumentDays, 'instrument_day')
                ->selectRaw("DATE_TRUNC('month', instrument_day.entry_date) AS month")
                ->selectRaw('AVG(instrument_day.score) AS score')
                ->selectRaw('PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY instrument_day.score) AS q25')
                ->selectRaw('PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY instrument_day.score) AS median')
                ->selectRaw('PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY instrument_day.score) AS q75')
                ->selectRaw('MIN(instrument_day.score) AS minimum')
                ->selectRaw('MAX(instrument_day.score) AS maximum')
                ->selectRaw('COUNT(*) AS observations')
                ->groupByRaw("DATE_TRUNC('month', instrument_day.entry_date)")
                ->orderBy('month')
                ->get()
                ->keyBy(fn (object $row): string => Carbon::parse($row->month)->format('Y-m'));

            return collect(range(0, 35))->map(function (int $offset) use ($firstMonth, $monthly): array {
                $month = $firstMonth->copy()->addMonths($offset);
                $row = $monthly->get($month->format('Y-m'));

                return [
                    'month' => $month->format('Y-m'),
                    'label' => $month->locale(app()->getLocale())->translatedFormat('M y'),
                    'value' => $row !== null ? round((float) $row->score, 2) : null,
                    'q25' => $row !== null ? round((float) $row->q25, 2) : null,
                    'median' => $row !== null ? round((float) $row->median, 2) : null,
                    'q75' => $row !== null ? round((float) $row->q75, 2) : null,
                    'minimum' => $row !== null ? round((float) $row->minimum, 2) : null,
                    'maximum' => $row !== null ? round((float) $row->maximum, 2) : null,
                    'observations' => $row !== null ? (int) $row->observations : 0,
                ];
            })->all();
        });
    }

    private function loadMacroCards(): array
    {
        $series = static fn ($rows): array => collect($rows ?? [])->map(fn (object $row): array => [
            'label' => Carbon::parse($row->bar_time)->format('d.m.'),
            'value' => is_numeric($row->close) ? (float) $row->close : null,
        ])->filter(fn (array $point): bool => $point['value'] !== null)->values()->all();
        $daxLevels = DB::table('instruments as instrument')
            ->join('price_bars as bar', 'bar.instrument_id', '=', 'instrument.id')
            ->where('instrument.symbol', 'EXS1:XETR')->where('bar.interval', '1d')
            ->where('bar.source', 'twelve_data')
            ->where('bar.bar_time', '>=', now()->subYear())
            ->orderBy('bar.bar_time')->get(['bar.close', 'bar.bar_time']);
        $daxMedian = (float) $daxLevels->pluck('close')->filter(fn ($value) => is_numeric($value) && (float) $value > 0)->median();
        if ($daxMedian > 0) {
            $daxLevels = $daxLevels->filter(fn (object $row): bool =>
                is_numeric($row->close)
                && (float) $row->close >= $daxMedian * .5
                && (float) $row->close <= $daxMedian * 1.5
            )->values();
        }
        $daxSeries = $series($daxLevels);
        $aiSeries = DB::table('backtest_trades as trade')
            ->join('backtest_runs as run', 'run.id', '=', 'trade.backtest_run_id')
            ->whereNotNull('trade.ki_score')->where('trade.entry_date', '>=', now()->subYear()->toDateString())
            ->whereIn('run.status', ['completed', 'completed_with_errors'])
            ->selectRaw('trade.entry_date AS day, AVG(trade.ki_score) AS score')
            ->groupBy('trade.entry_date')->orderBy('trade.entry_date')->get()
            ->map(fn (object $point): array => ['day' => (string) $point->day, 'label' => Carbon::parse($point->day)->format('d.m.'), 'value' => (float) $point->score])
            ->values();
        $dailyPredictionScores = DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('prediction.prediction_score')
            ->where('prediction.prediction_time', '>=', now()->subYear())
            ->selectRaw('DATE(prediction.prediction_time) AS day, AVG(prediction.prediction_score) AS score')
            ->groupByRaw('DATE(prediction.prediction_time)')
            ->orderBy('day')
            ->get()
            ->map(fn (object $point): array => [
                'day' => (string) $point->day,
                'label' => Carbon::parse($point->day)->format('d.m.'),
                'value' => (float) (\App\Support\AiScore::toTen($point->score) ?? 0),
            ]);
        // Daily predictions supersede an older backtest sample for the same
        // calendar day, while the backtest remains the long-term history.
        $aiSeries = $aiSeries->concat($dailyPredictionScores)->keyBy('day')->sortKeys()->values();
        // Seven-point trailing median removes single-day backtest noise while
        // keeping the direction and timing of the KI-score visible.
        $aiSeries = $aiSeries->map(function (array $point, int $index) use ($aiSeries): array {
            $window = $aiSeries->slice(max(0, $index - 6), 7)->pluck('value')->filter(fn ($value) => is_numeric($value));
            return ['label' => $point['label'], 'value' => round((float) ($window->median() ?? $point['value']), 2)];
        })->values()->all();
        $daxCompareSeries = count($aiSeries) > 0 ? array_slice($daxSeries, -count($aiSeries)) : [];
        $vdaxId = DB::table('instruments')
            ->where('isin', 'A0DMX9')
            ->orWhere('symbol', 'VDAX')
            ->value('id');
        $volatility = $vdaxId
            ? DB::table('price_bars')
                ->where('instrument_id', $vdaxId)
                ->where('interval', '1d')
                ->where('bar_time', '>=', now()->subYear())
                ->orderBy('bar_time')
                ->get(['close', 'bar_time'])
                ->map(fn (object $row): array => [
                    'label' => Carbon::parse($row->bar_time)->format('d.m.'),
                    'value' => is_numeric($row->close) ? (float) $row->close : null,
                ])
                ->filter(fn (array $point): bool => $point['value'] !== null && $point['value'] > 0 && $point['value'] < 150)
                ->values()
                ->all()
            : [];
        if ($volatility === []) {
            $dax = $daxLevels->values();
            $prices = $dax->map(fn (object $row): ?float => is_numeric($row->close) ? (float) $row->close : null)->filter(fn ($value) => $value !== null)->values();
            $returns = collect();
            for ($index = 1; $index < $prices->count(); $index++) $returns->push($prices[$index - 1] > 0 ? ($prices[$index] / $prices[$index - 1]) - 1 : null);
            for ($index = 20; $index < $returns->count(); $index++) {
                $window = $returns->slice($index - 20, 20)->filter(fn ($value) => $value !== null)->values();
                $mean = $window->avg();
                $variance = $window->map(fn (float $value): float => ($value - $mean) ** 2)->avg() ?? 0.0;
                $volatility[] = ['label' => Carbon::parse($dax[$index + 1]->bar_time)->format('d.m.'), 'value' => sqrt($variance) * sqrt(252) * 100];
            }
            // Keep the chart responsive while retaining the full three-year window.
            if (count($volatility) > 260) {
                $step = max(1, (int) floor(count($volatility) / 260));
                $volatility = collect($volatility)->filter(fn (array $_, int $index): bool => $index % $step === 0)->values()->all();
            }
        }
        $spyBars = DB::table('instruments as instrument')
            ->join('price_bars as bar', 'bar.instrument_id', '=', 'instrument.id')
            ->where('instrument.symbol', 'SPY')->where('bar.interval', '1d')->where('bar.source', 'twelve_data')
            ->where('bar.bar_time', '>=', now()->subYear()->subMonths(2))
            ->orderBy('bar.bar_time')->get(['bar.close', 'bar.bar_time'])->map(fn (object $row): array => [
                'day' => Carbon::parse($row->bar_time)->toDateString(), 'close' => (float) $row->close,
            ])->values();
        $sp500Series = $spyBars->map(fn (array $bar): array => [
            'label' => Carbon::parse($bar['day'])->format('d.m.Y'),
            'value' => round((float) $bar['close'], 2),
        ])->all();
        $nasdaqBars = DB::table('instruments as instrument')
            ->join('price_bars as bar', 'bar.instrument_id', '=', 'instrument.id')
            ->where('instrument.symbol', 'QQQ')->where('bar.interval', '1d')->where('bar.source', 'twelve_data')
            ->where('bar.bar_time', '>=', now()->subYear()->subMonths(2))
            ->orderBy('bar.bar_time')->get(['bar.close', 'bar.bar_time']);
        $nasdaqSeries = $series($nasdaqBars);
        return collect([
            ['key' => 'dax-backtest', 'title' => __('DAX · Kursverlauf'), 'subtitle' => __('Letzter DAX-ETF-Kurs von Twelve Data'), 'unit' => ' EUR', 'series' => [['name' => __('DAX-ETF'), 'color' => '#06b6d4', 'points' => $daxSeries, 'axis' => 'price', 'display_unit' => ' EUR']]],
            ['key' => 'sp500-backtest', 'title' => __('S&P 500 · Kursverlauf'), 'subtitle' => __('Letzter SPY-Kurs von Twelve Data'), 'unit' => ' USD', 'series' => [['name' => __('S&P 500 ETF'), 'color' => '#38bdf8', 'points' => $sp500Series, 'axis' => 'price', 'display_unit' => ' USD']]],
            ['key' => 'nasdaq-backtest', 'title' => __('NASDAQ · Kursverlauf'), 'subtitle' => __('Letzter QQQ-Kurs von Twelve Data'), 'unit' => ' USD', 'series' => [['name' => __('NASDAQ-100 ETF'), 'color' => '#a78bfa', 'points' => $nasdaqSeries, 'axis' => 'price', 'display_unit' => ' USD']]],
        ])->filter(fn (array $card): bool => collect($card['series'])->contains(
            fn (array $series): bool => count($series['points'] ?? []) > 0
        ))->values()->all();
    }

    private function loadSignalTransitionStats(): array
    {
        return Cache::remember('markets.signal-transition-stats.5d.v3', now()->addMinutes(2), function (): array {
            $history = DB::table('predictions as prediction')
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.type', 'stock')
                ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
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

            // Match the dashboard card exactly: latest prediction per stock,
            // limited to predictions from the last 48 hours.
            $latestPredictionIds = DB::table('predictions')
                ->selectRaw('instrument_id, MAX(id) AS prediction_id')
                ->groupBy('instrument_id');
            $latestSignals = DB::table('predictions as current_prediction')
                ->joinSub($latestPredictionIds, 'latest_prediction', fn ($join) =>
                    $join->on('latest_prediction.prediction_id', '=', 'current_prediction.id'))
                ->join('instruments as current_instrument', 'current_instrument.id', '=', 'current_prediction.instrument_id')
                ->where('current_instrument.type', 'stock')
                ->where(fn ($query) => $query->whereNull('current_instrument.risk_status')->orWhere('current_instrument.risk_status', '<>', 'sleep'))
                ->where('current_instrument.is_active', true)
                ->whereNull('current_instrument.deleted_at')
                ->where('current_prediction.prediction_time', '>=', now()->subHours(48))
                ->whereIn(DB::raw('UPPER(current_prediction.signal)'), ['SELL', 'HOLD', 'WAIT', 'BUY'])
                ->select(['current_prediction.instrument_id'])
                ->selectRaw("UPPER(current_prediction.signal) AS signal");
            $distribution = DB::query()
                ->fromSub($latestSignals, 'latest_signal')
                ->groupBy('signal')
                ->get(['signal', DB::raw('COUNT(*) AS signal_count')])
                ->mapWithKeys(fn (object $row): array => [(string) $row->signal => (int) $row->signal_count])
                ->all();
            $distribution = collect(['SELL', 'HOLD', 'WAIT', 'BUY'])
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

    private function loadRuleBasedMarketAnalysis(array $instrumentIds = []): array
    {
        $scopeKey = $instrumentIds === [] ? 'global' : sha1(implode(',', $instrumentIds));

        return Cache::remember('markets.rule-based-analysis.v2.'.$scopeKey, now()->addMinutes(2), function () use ($instrumentIds): array {
            $latestPredictions = DB::table('predictions as prediction')
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->join('trained_models as model', 'model.id', '=', 'prediction.trained_model_id')
                ->where('instrument.type', 'stock')
                ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->where('prediction.ai_type', 'horizon')
                ->where('prediction.position_side', 'long')
                ->where('model.feature_set_version', 'triple_daily_macro_v1')
                ->where('prediction.prediction_time', '>=', now()->subDays(7))
                ->when($instrumentIds !== [], fn ($query) => $query->whereIn('instrument.id', $instrumentIds))
                ->selectRaw('DISTINCT ON (prediction.instrument_id) prediction.instrument_id')
                ->addSelect([
                    'instrument.symbol', 'instrument.name', 'instrument.sector',
                    'prediction.prediction_time', 'prediction.recommendation_class',
                    'prediction.signal', 'prediction.quality_gate_passed',
                    'prediction.confidence', 'prediction.prediction_score',
                    'prediction.risk_score', 'prediction.drawdown_risk_factor',
                ])
                ->selectRaw('COALESCE(prediction.market_return_20d, prediction.market_return_10d, prediction.market_return_5d) * 100 AS expected_return_percent')
                ->orderBy('prediction.instrument_id')
                ->orderByDesc('prediction.prediction_time')
                ->orderByDesc('prediction.id')
                ->get();

            $count = $latestPredictions->count();
            $returns = $latestPredictions->pluck('expected_return_percent')
                ->filter(fn ($value): bool => is_numeric($value))
                ->map(fn ($value): float => (float) $value);
            $positive = $returns->filter(fn (float $value): bool => $value > 0)->count();
            $negative = $returns->filter(fn (float $value): bool => $value < 0)->count();
            $averageReturn = (float) ($returns->avg() ?? 0);
            $qualityCount = $latestPredictions->filter(fn (object $row): bool => (bool) $row->quality_gate_passed)->count();
            $qualityRate = $count > 0 ? $qualityCount / $count * 100 : 0;
            $positiveRate = $returns->count() > 0 ? $positive / $returns->count() * 100 : 0;
            $outlook = $qualityRate >= 50 && $averageReturn >= 1
                ? 'BULLISH'
                : ($averageReturn <= -1 ? 'BEARISH' : 'NEUTRAL');
            $riskLevel = $qualityRate < 25 ? 'HIGH' : ($qualityRate < 50 ? 'MEDIUM' : 'LOW');

            $sectors = $latestPredictions->groupBy(fn (object $row): string => (string) ($row->sector ?: __('Unbekannt')))
                ->map(function ($items, string $sector): array {
                    $values = $items->pluck('expected_return_percent')->filter(fn ($value): bool => is_numeric($value));

                    return [
                        'sector' => $sector,
                        'count' => $items->count(),
                        'return' => (float) ($values->avg() ?? 0),
                        'quality' => $items->filter(fn (object $row): bool => (bool) $row->quality_gate_passed)->count(),
                    ];
                })
                ->sortByDesc('return')
                ->values();
            $bestSector = $sectors->first();
            $weakestSector = $sectors->last();
            $ranked = $latestPredictions->filter(fn (object $row): bool => is_numeric($row->expected_return_percent))
                ->sortByDesc(fn (object $row): float => (float) $row->expected_return_percent)
                ->values();
            $formatStock = static fn (object $row): string => sprintf(
                '%s (%s): erwartete 20-Tage-Rendite %+.1f %%, KI-Score %.1f, Konfidenz %.0f %%.',
                $row->name ?: $row->symbol,
                $row->symbol,
                (float) $row->expected_return_percent,
                (float) ($row->prediction_score <= 1 ? $row->prediction_score * 100 : ($row->prediction_score <= 10 ? $row->prediction_score * 10 : $row->prediction_score)),
                (float) ($row->confidence > 1 ? $row->confidence : $row->confidence * 100),
            );
            $formatRisk = static fn (object $row): string => sprintf(
                '%s (%s): erwartete 20-Tage-Rendite %+.1f %%; Quality Gate %s.',
                $row->name ?: $row->symbol,
                $row->symbol,
                (float) $row->expected_return_percent,
                $row->quality_gate_passed ? 'bestanden' : 'nicht bestanden',
            );

            $opportunities = $ranked->take(5)->map($formatStock)->values()->all();
            $risks = $ranked->reverse()->take(5)->map($formatRisk)->values()->all();
            $watchlist = collect();
            if ($bestSector) {
                $watchlist->push([
                    'symbol' => __('Sektor'),
                    'reason' => sprintf('%s führt mit einer durchschnittlichen Erwartung von %+.1f %% bei %d Aktien.', $bestSector['sector'], $bestSector['return'], $bestSector['count']),
                ]);
            }
            if ($weakestSector) {
                $watchlist->push([
                    'symbol' => __('Sektor-Risiko'),
                    'reason' => sprintf('%s ist mit durchschnittlich %+.1f %% derzeit der schwächste Sektor.', $weakestSector['sector'], $weakestSector['return']),
                ]);
            }
            $watchlist->push([
                'symbol' => __('Modellqualität'),
                'reason' => sprintf('%d von %d aktuellen Prognosen bestehen das Quality Gate (%s %%).', $qualityCount, $count, number_format($qualityRate, 0, ',', '.')),
            ]);

            $headline = match ($outlook) {
                'BULLISH' => __('Positive Marktbreite mit bestätigter Modellqualität'),
                'BEARISH' => __('Negative Renditeerwartung prägt das aktuelle Lagebild'),
                default => __('Gemischte Marktbreite ohne belastbaren Richtungskonsens'),
            };
            $summary = sprintf(
                '%d von %d aktuellen Prognosen weisen eine positive Renditeerwartung auf, %d eine negative. Die durchschnittliche 20-Tage-Erwartung liegt bei %+.1f %%. Da nur %d von %d Prognosen das Quality Gate bestehen, bleibt die Einordnung %s bei %s Modellrisiko.',
                $positive, $count, $negative, $averageReturn, $qualityCount, $count,
                match ($outlook) { 'BULLISH' => 'positiv', 'BEARISH' => 'negativ', default => 'neutral' },
                match ($riskLevel) { 'LOW' => 'niedrigem', 'MEDIUM' => 'erhöhtem', default => 'hohem' },
            );
            $breadth = $bestSector && $weakestSector
                ? sprintf('Stärkster Sektor: %s (%+.1f %%). Schwächster Sektor: %s (%+.1f %%). Berechnet aus dem aktuellen Modellstand triple_daily_macro_v1.', $bestSector['sector'], $bestSector['return'], $weakestSector['sector'], $weakestSector['return'])
                : __('Für den Sektorvergleich liegen noch nicht genügend aktuelle Prognosen vor.');

            return [
                'date' => now()->toDateTimeString(),
                'model' => 'Regelbasiert · ohne OpenAI',
                'outlook' => $outlook,
                'confidence' => (int) round($qualityRate),
                'riskLevel' => $riskLevel,
                'headline' => $headline,
                'summary' => $summary,
                'breadth' => $breadth,
                'sectors' => $sectors->all(),
                'opportunities' => $opportunities,
                'risks' => $risks,
                'watchlist' => $watchlist->all(),
                'metrics' => [
                    ['label' => __('Abdeckung'), 'value' => (string) $count, 'detail' => __('aktuelle Aktien')],
                    ['label' => __('Positive Breite'), 'value' => number_format($positiveRate, 0, ',', '.').' %', 'detail' => "{$positive} von {$returns->count()}"],
                    ['label' => __('Ø Erwartung'), 'value' => sprintf('%+.1f %%', $averageReturn), 'detail' => __('20 Tage')],
                    ['label' => __('Quality Gate'), 'value' => number_format($qualityRate, 0, ',', '.').' %', 'detail' => "{$qualityCount} von {$count}"],
                ],
            ];
        });
    }

    private function loadExternalMarketAnalysis(array $ruleBasedAnalysis): ?array
    {
        $analysis = DB::table('daily_market_ai_analyses')
            ->orderByDesc('analysis_date')
            ->orderByDesc('id')
            ->first();
        if (! $analysis) {
            return null;
        }

        $raw = is_string($analysis->raw_response ?? null)
            ? json_decode($analysis->raw_response, true)
            : (array) ($analysis->raw_response ?? []);
        if (! data_get($raw, 'external_research', false)) {
            return null;
        }

        $payloadSources = collect(data_get($raw, 'output.sources', []));
        $toolSources = collect(data_get($raw, 'web_sources', []));
        $sources = $payloadSources->concat($toolSources)
            ->filter(fn ($source): bool => is_array($source) && filter_var($source['url'] ?? null, FILTER_VALIDATE_URL) !== false)
            ->unique('url')
            ->take(16)
            ->map(fn (array $source): array => [
                'title' => (string) ($source['title'] ?? parse_url((string) $source['url'], PHP_URL_HOST)),
                'publisher' => (string) ($source['publisher'] ?? parse_url((string) $source['url'], PHP_URL_HOST)),
                'url' => (string) $source['url'],
                'published_at' => $source['published_at'] ?? null,
                'used_for' => (string) ($source['used_for'] ?? ''),
            ])
            ->values()
            ->all();

        return [
            'date' => $analysis->analysis_date,
            'model' => $analysis->model,
            'is_external_ai' => true,
            'outlook' => $analysis->market_outlook,
            'confidence' => (int) $analysis->confidence,
            'riskLevel' => $analysis->risk_level,
            'headline' => $analysis->headline,
            'summary' => $analysis->executive_summary,
            'breadth' => $analysis->breadth_analysis,
            'sectors' => $this->decodeJson($analysis->sector_analysis),
            'opportunities' => $this->decodeJson($analysis->opportunities),
            'risks' => $this->decodeJson($analysis->risks),
            'watchlist' => $this->decodeJson($analysis->watchlist),
            'sources' => $sources,
            'metrics' => $ruleBasedAnalysis['metrics'] ?? [],
        ];
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
        return Cache::remember('dashboard_index_market_bars_v2', now()->addSeconds(30), function (): array {
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
                $price = $latest && is_numeric($latest->close) ? (float) $latest->close : null;
                $latestDay = $latest ? Carbon::parse($latest->bar_time)->toDateString() : null;
                $previousDaily = $symbolBars
                    ->first(fn (object $bar): bool =>
                        $bar->interval === '1d'
                        && is_numeric($bar->close)
                        && $latestDay !== null
                        && Carbon::parse($bar->bar_time)->toDateString() < $latestDay
                        // Reject incorrectly assigned or differently scaled
                        // index rows (for example 47 instead of 26,000).
                        && ($price === null || ((float) $bar->close >= $price * .5 && (float) $bar->close <= $price * 2))
                    );
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
