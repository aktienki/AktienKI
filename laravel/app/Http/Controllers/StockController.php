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
            ->except(['id', 'instrument_id', 'personalized_signal', 'explanation', 'metadata', 'created_at', 'updated_at'])
            ->reject(fn ($value) => $value === null)
            ->all();
        $chartDataUrl = route('stocks.chart-data', $requestedPredictionId > 0
            ? ['symbol' => $instrument->symbol, 'prediction' => $requestedPredictionId]
            : ['symbol' => $instrument->symbol]);

        return view('stocks.show', compact(
            'instrument',
            'prediction',
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
                ->orderBy('bar_time')
                ->get()
                ->values();
        }

        return $query
            ->orderByDesc('bar_time')
            ->limit(66)
            ->get()
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
