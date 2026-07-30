<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use App\Services\YahooFinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class StockController extends Controller
{
    public function chartAnalysis(string $symbol): View
    {
        $instrument = $this->instrument($symbol);
        $exchange = $instrument->exchange_id
            ? DB::table('exchanges')->where('id', $instrument->exchange_id)->first()
            : null;
        $indicators = DB::table('technical_indicators')
            ->where('instrument_id', $instrument->id)
            ->where('interval', '1d')
            ->where('bar_time', '>=', now()->subYears(3)->startOfDay())
            ->orderByDesc('bar_time')
            ->get([
                'bar_time', 'sma_20', 'sma_50', 'sma_200', 'ema_20', 'ema_50',
                'bollinger_upper', 'bollinger_middle', 'bollinger_lower', 'bollinger_width',
                'rsi_14', 'macd', 'macd_signal', 'macd_histogram', 'adx_14', 'atr_14',
                'stochastic_k', 'stochastic_d', 'volatility_20', 'momentum_10',
            ])
            ->reverse()
            ->values();
        $features = $indicators->isEmpty()
            ? collect()
            : DB::table('feature_store')
                ->where('instrument_id', $instrument->id)
                ->where('interval', '1d')
                ->whereIn('bar_time', $indicators->pluck('bar_time'))
                ->get(['bar_time', 'close', 'target_return_20d'])
                ->keyBy(fn (object $row): string => CarbonImmutable::parse($row->bar_time)->toIso8601String());

        $chartRows = $indicators->map(function (object $indicator) use ($features): array {
            $time = CarbonImmutable::parse($indicator->bar_time);
            $feature = $features->get($time->toIso8601String());

            return [
                'x' => $time->getTimestampMs(),
                'c' => $this->number($feature?->close),
                'sma20' => $this->number($indicator?->sma_20),
                'sma50' => $this->number($indicator?->sma_50),
                'sma200' => $this->number($indicator?->sma_200),
                'ema20' => $this->number($indicator?->ema_20),
                'ema50' => $this->number($indicator?->ema_50),
                'bbUpper' => $this->number($indicator?->bollinger_upper),
                'bbMiddle' => $this->number($indicator?->bollinger_middle),
                'bbLower' => $this->number($indicator?->bollinger_lower),
                'bbWidth' => $this->number($indicator?->bollinger_width),
                'rsi' => $this->number($indicator?->rsi_14),
                'macd' => $this->number($indicator?->macd),
                'macdSignal' => $this->number($indicator?->macd_signal),
                'macdHistogram' => $this->number($indicator?->macd_histogram),
                'adx' => $this->number($indicator?->adx_14),
                'atr' => $this->number($indicator?->atr_14),
                'stochK' => $this->number($indicator?->stochastic_k),
                'stochD' => $this->number($indicator?->stochastic_d),
                'volatility' => $this->number($indicator?->volatility_20),
                'momentum10' => $this->number($indicator?->momentum_10),
                'targetReturn20d' => $this->number($feature?->target_return_20d),
            ];
        });

        $indicatorDefinitions = [
            'rsi_14' => ['label' => 'RSI 14', 'field' => 'rsi', 'unit' => ''],
            'adx_14' => ['label' => 'ADX 14', 'field' => 'adx', 'unit' => ''],
            'stochastic_k' => ['label' => 'Stochastik %K', 'field' => 'stochK', 'unit' => ''],
            'volatility_20' => ['label' => __('Volatilität 20T'), 'field' => 'volatility', 'unit' => '%'],
            'atr_14_pct' => ['label' => 'ATR 14', 'field' => 'atr', 'unit' => $instrument->currency ?: ''],
            'bollinger_width' => ['label' => __('Bollinger-Bandbreite'), 'field' => 'bbWidth', 'unit' => '%'],
            'macd_histogram_pct' => ['label' => 'MACD Histogramm', 'field' => 'macdHistogram', 'unit' => ''],
            'momentum_10_pct' => ['label' => __('Momentum 10T'), 'field' => 'momentum10Pct', 'unit' => '%'],
        ];
        $valueFor = function (?array $row, string $field): ?float {
            if (! $row) {
                return null;
            }
            $close = (float) ($row['c'] ?? 0);

            return match ($field) {
                'atrPct' => $close > 0 && $row['atr'] !== null ? $row['atr'] / $close : null,
                'macdHistogramPct' => $close > 0 && $row['macdHistogram'] !== null ? $row['macdHistogram'] / $close : null,
                'momentum10Pct' => abs($close - (float) ($row['momentum10'] ?? 0)) > 0.000001 && $row['momentum10'] !== null
                    ? $row['momentum10'] / ($close - $row['momentum10'])
                    : null,
                default => isset($row[$field]) && is_numeric($row[$field]) ? (float) $row[$field] : null,
            };
        };
        $currentRow = $chartRows->last();
        $fiveDayRow = $chartRows->count() >= 6 ? $chartRows->get($chartRows->count() - 6) : null;
        $indicatorCards = collect($indicatorDefinitions)->map(function (array $definition) use ($chartRows, $currentRow, $fiveDayRow, $valueFor): array {
            $scale = $definition['unit'] === '%' ? 100.0 : 1.0;
            $currentRawValue = $valueFor($currentRow, $definition['field']);
            $fiveDayRawValue = $valueFor($fiveDayRow, $definition['field']);
            $fiveDayChange = $currentRawValue !== null && $fiveDayRawValue !== null
                ? ($currentRawValue - $fiveDayRawValue) * $scale
                : null;
            $points = $chartRows->map(function (array $row) use ($definition, $scale, $valueFor): ?array {
                $rawValue = $valueFor($row, $definition['field']);
                if ($rawValue === null || ! is_numeric($row['targetReturn20d'])) {
                    return null;
                }
                $return = (float) $row['targetReturn20d'];

                return [
                    'x' => $rawValue * $scale,
                    'y' => $return * 100,
                    'up' => $return > 0,
                    'date' => CarbonImmutable::createFromTimestampMs($row['x'])->format('d.m.Y'),
                ];
            })->filter()->values();
            $nearby = $currentRawValue === null
                ? collect()
                : $points->sortBy(fn (array $point): float => abs($point['x'] - ($currentRawValue * $scale)))
                    ->take(min(40, $points->count()));
            $riseProbability = $nearby->isEmpty()
                ? null
                : ($nearby->where('up', true)->count() / $nearby->count()) * 100;

            return [
                ...$definition,
                'currentValue' => $currentRawValue === null ? null : $currentRawValue * $scale,
                'fiveDayChange' => $fiveDayChange,
                'fiveDayDirection' => $fiveDayChange === null
                    ? null
                    : (abs($fiveDayChange) < 0.000001 ? 'flat' : ($fiveDayChange > 0 ? 'up' : 'down')),
                'currentProbability' => $riseProbability,
                'currentFallProbability' => $riseProbability === null ? null : 100 - $riseProbability,
                'comparisonSamples' => $nearby->count(),
                'points' => $points,
            ];
        })->values();

        return view('stocks.chart-analysis', compact(
            'instrument', 'exchange', 'indicatorCards'
        ));
    }

    public function show(
        Request $request,
        string $symbol,
        PersonalizedSignalService $personalizedSignals,
        YahooFinanceService $yahooFinance,
    ): View
    {
        $instrument = $this->instrument($symbol);

        $signalSql = $personalizedSignals->sql('prediction', auth()->user());
        $requestedPredictionId = $request->integer('prediction');
        $predictionQuery = DB::table('predictions as prediction')
            ->where('prediction.instrument_id', $instrument->id)
            ->select('prediction.*')
            ->selectRaw("{$signalSql} AS personalized_signal");

        if ($requestedPredictionId > 0) {
            $predictionQuery->where('prediction.id', $requestedPredictionId);
        } else {
            $predictionQuery
                ->orderByDesc('prediction.prediction_time')
                ->orderByDesc('prediction.id');
        }

        $prediction = $predictionQuery->first();
        abort_if($requestedPredictionId > 0 && ! $prediction, 404);
        $modelQuality = $prediction?->trained_model_id
            ? DB::table('trained_models as trained_model')
                ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
                ->leftJoin('model_quality_rankings as model_quality', function ($join): void {
                    $join->on('model_quality.trained_model_id', '=', 'trained_model.id')
                        ->whereRaw('model_quality.id = (
                            SELECT MAX(latest_model_quality.id)
                            FROM model_quality_rankings AS latest_model_quality
                            WHERE latest_model_quality.trained_model_id = trained_model.id
                        )');
                })
                ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
                ->where('trained_model.id', $prediction->trained_model_id)
                ->first([
                    'model_definition.public_alias as model_alias',
                    'model_quality.quality_score',
                    'model_quality.eligible',
                    'quality_tier.code as tier_code',
                    'quality_tier.name as tier_name',
                ])
            : null;
        $aiAssessment = DB::table('stock_ai_assessments')
            ->where('instrument_id', $instrument->id)
            ->when($requestedPredictionId > 0, fn ($query) => $query->where('prediction_id', $requestedPredictionId))
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->first();
        $aiAssessmentOpportunities = $this->decodeJson($aiAssessment?->opportunities);
        $aiAssessmentRisks = $this->decodeJson($aiAssessment?->risks);
        $aiAssessmentFactors = $this->decodeJson($aiAssessment?->key_factors);
        $chartFocusAt = $requestedPredictionId > 0 && $prediction?->prediction_time
            ? CarbonImmutable::parse($prediction->prediction_time)
            : null;
        $returnTo = $request->query('return_to');
        $returnTo = is_string($returnTo)
            && Str::startsWith($returnTo, '/')
            && ! Str::startsWith($returnTo, '//')
                ? $returnTo
                : null;
        $returnLabel = $returnTo && Str::startsWith($returnTo, '/watchlists')
            ? __('Zurück zur Watchlist')
            : ($returnTo && Str::startsWith($returnTo, '/predictions')
                ? __('Zurück zu Prognosen')
                : null);

        $fundamental = DB::table('instrument_fundamentals')
            ->where('instrument_id', $instrument->id)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first();

        $fundamentalData = $this->decodeJson($fundamental?->data);
        $instrumentMeta = $this->decodeJson($instrument->meta);
        $predictionExplanation = $this->decodeJson($prediction?->explanation);
        $predictionMetadata = $this->decodeJson($prediction?->metadata);
        $sectorRankings = $this->sectorRankings($instrument, $fundamentalData);

        ['candles' => $chartCandles, 'source' => $chartSource] = $this->chartSeries($instrument, $yahooFinance, $chartFocusAt);
        $watchlistEntry = $this->watchlistEntry($instrument->id);
        $userWatchlists = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        $instrumentWatchlistIds = $userWatchlists->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->where('instrument_id', $instrument->id)
                ->whereIn('watchlist_id', $userWatchlists->pluck('id'))
                ->pluck('watchlist_id')
                ->map(fn ($id) => (int) $id);

        $predictionData = collect((array) $prediction)
            ->except(['id', 'instrument_id', 'trained_model_id', 'personalized_signal', 'explanation', 'metadata', 'created_at', 'updated_at'])
            ->reject(fn ($value) => $value === null)
            ->all();
        $chartDataUrl = route('stocks.chart-data', $requestedPredictionId > 0
            ? ['symbol' => $instrument->symbol, 'prediction' => $requestedPredictionId]
            : ['symbol' => $instrument->symbol]);

        return view('stocks.show', compact(
            'instrument',
            'prediction',
            'modelQuality',
            'aiAssessment',
            'aiAssessmentOpportunities',
            'aiAssessmentRisks',
            'aiAssessmentFactors',
            'predictionData',
            'predictionExplanation',
            'predictionMetadata',
            'fundamental',
            'fundamentalData',
            'sectorRankings',
            'instrumentMeta',
            'chartCandles',
            'chartSource',
            'chartFocusAt',
            'chartDataUrl',
            'requestedPredictionId',
            'returnTo',
            'returnLabel',
            'watchlistEntry',
            'userWatchlists',
            'instrumentWatchlistIds',
        ));
    }

    public function chartData(Request $request, string $symbol, YahooFinanceService $yahooFinance): JsonResponse
    {
        $instrument = $this->instrument($symbol);
        $requestedPredictionId = $request->integer('prediction');
        $chartFocusAt = null;

        if ($requestedPredictionId > 0) {
            $predictionTime = DB::table('predictions')
                ->where('id', $requestedPredictionId)
                ->where('instrument_id', $instrument->id)
                ->value('prediction_time');
            abort_unless($predictionTime, 404);
            $chartFocusAt = CarbonImmutable::parse($predictionTime);
        }

        $series = $this->chartSeries($instrument, $yahooFinance, $chartFocusAt);

        return response()->json([
            'symbol' => $instrument->symbol,
            'candles' => $series['candles']->values(),
            'source' => $series['source'],
            'watchlist_entry' => $this->watchlistEntry($instrument->id),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function instrument(string $symbol): object
    {
        $instrument = DB::table('instruments')
            ->where('type', 'stock')
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(symbol) = ?', [strtoupper($symbol)])
            ->first();

        abort_unless($instrument, 404);

        return $instrument;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function chartSeries(
        object $instrument,
        YahooFinanceService $yahooFinance,
        ?CarbonImmutable $focusAt = null,
    ): array
    {
        $bars = $this->dailyBars((int) $instrument->id, $focusAt);

        if ($bars->count() < ($focusAt ? 50 : 66)) {
            try {
                $downloaded = $yahooFinance->dailyCandles(
                    $instrument->provider_symbol ?: $instrument->symbol,
                    $focusAt ? 140 : 66,
                );

                if ($downloaded) {
                    $now = now();
                    $rows = collect($downloaded)->map(fn (array $bar) => [
                        'instrument_id' => (int) $instrument->id,
                        'interval' => '1d',
                        'bar_time' => CarbonImmutable::createFromTimestampUTC($bar['timestamp']),
                        'open' => $bar['open'],
                        'high' => $bar['high'],
                        'low' => $bar['low'],
                        'close' => $bar['close'],
                        'adjusted_close' => $bar['adjusted_close'],
                        'volume' => $bar['volume'],
                        'source' => 'yahoo',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('price_bars')->upsert(
                        $rows,
                        ['instrument_id', 'interval', 'bar_time'],
                        ['open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at'],
                    );
                    $bars = $this->dailyBars((int) $instrument->id, $focusAt);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'candles' => $bars->map(fn ($bar) => [
                'x' => CarbonImmutable::parse($bar->bar_time)->getTimestampMs(),
                'y' => [
                    (float) $bar->open,
                    (float) $bar->high,
                    (float) $bar->low,
                    (float) $bar->close,
                ],
            ]),
            'source' => $bars->isEmpty() ? 'unavailable' : ($bars->every(fn ($bar) => $bar->source === 'yahoo') ? 'yahoo' : 'price_bars'),
        ];
    }

    private function dailyBars(int $instrumentId, ?CarbonImmutable $focusAt = null)
    {
        $query = DB::table('price_bars')
            ->where('instrument_id', $instrumentId)
            ->where('interval', '1d');

        if ($focusAt) {
            return $query
                ->whereBetween('bar_time', [
                    $focusAt->subDays(50)->startOfDay(),
                    $focusAt->addDays(50)->endOfDay(),
                ])
                ->orderByDesc('bar_time')
                ->get()
                ->unique(fn ($bar) => CarbonImmutable::parse($bar->bar_time)->format('Y-m-d'))
                ->sortBy('bar_time')
                ->values();
        }

        return $query
            ->orderByDesc('bar_time')
            // A provider can persist the same trading day with a different
            // time component. Fetch enough rows, then keep exactly one OHLC
            // record per calendar/trading day.
            ->limit(160)
            ->get()
            ->unique(fn ($bar) => CarbonImmutable::parse($bar->bar_time)->format('Y-m-d'))
            ->take(66)
            ->reverse()
            ->values();
    }

    private function watchlistEntry(int $instrumentId): ?array
    {
        $entry = DB::table('watchlist_items as item')
            ->join('watchlists as watchlist', 'watchlist.id', '=', 'item.watchlist_id')
            ->where('watchlist.user_id', auth()->id())
            ->where('watchlist.active', true)
            ->where('item.instrument_id', $instrumentId)
            ->whereNotNull('item.entry_price')
            ->orderByDesc('watchlist.is_default')
            ->orderByDesc('item.added_at')
            ->select([
                'watchlist.name',
                'item.entry_price',
                'item.entry_price_at',
                'item.entry_currency',
            ])
            ->first();

        if (! $entry || ! is_numeric($entry->entry_price)) {
            return null;
        }

        return [
            'name' => $entry->name,
            'price' => (float) $entry->entry_price,
            'recorded_at' => $entry->entry_price_at,
            'currency' => $entry->entry_currency,
        ];
    }

    private function sectorRankings(object $instrument, array $fundamentalData): array
    {
        if (! $instrument->sector) {
            return [];
        }

        $latestFundamentalIds = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');

        $sectorFundamentals = DB::table('instruments as peer')
            ->joinSub($latestFundamentalIds, 'latest_fundamental', fn ($join) =>
                $join->on('latest_fundamental.instrument_id', '=', 'peer.id'))
            ->join('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->where('peer.type', 'stock')
            ->whereNull('peer.deleted_at')
            ->where('peer.sector', $instrument->sector)
            ->pluck('fundamental.data')
            ->map(fn ($data) => $this->decodeJson($data));

        $definitions = [
            'pe' => ['key' => 'trailingPE', 'direction' => 'asc', 'positive_only' => true],
            'dividend' => ['key' => 'dividendYield', 'direction' => 'desc', 'positive_only' => false],
        ];

        return collect($definitions)
            ->mapWithKeys(function (array $definition, string $name) use ($sectorFundamentals, $fundamentalData): array {
                $current = $fundamentalData[$definition['key']] ?? null;
                if (! is_numeric($current) || ($definition['positive_only'] && (float) $current <= 0)) {
                    return [$name => null];
                }

                $current = (float) $current;
                $values = $sectorFundamentals
                    ->pluck($definition['key'])
                    ->filter(fn ($value) => is_numeric($value)
                        && (! $definition['positive_only'] || (float) $value > 0))
                    ->map(fn ($value) => (float) $value)
                    ->values();

                if ($values->isEmpty()) {
                    return [$name => null];
                }

                $better = $values->filter(fn (float $value) =>
                    $definition['direction'] === 'asc' ? $value < $current : $value > $current
                )->count();

                return [$name => [
                    'rank' => $better + 1,
                    'total' => $values->count(),
                ]];
            })
            ->all();
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        return json_decode($value, true) ?: [];
    }

}
