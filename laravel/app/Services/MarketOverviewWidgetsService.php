<?php

namespace App\Services;

use App\Support\AiScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The index-market-tape and macro-indicator-cards data behind the full
 * Marktübersicht page (Livewire\Dashboard\MarketData, which delegates its
 * databaseMarkets()/loadMacroCards() here) - extracted so the concept
 * dashboard's market-report card can show the identical widgets without
 * duplicating the query logic.
 */
final class MarketOverviewWidgetsService
{
    public const INDEX_SYMBOLS = [
        'DAX' => '^GDAXI',
        'NASDAQ' => '^IXIC',
        'S&P 500' => '^GSPC',
        'Japan' => '^N225',
        'China' => '000001.SS',
    ];

    /** @return array<string, array{price: ?float, currency: string, change: ?float, candles: array}> */
    public function rawIndexMarkets(): array
    {
        return Cache::remember('dashboard_index_market_bars_v2', now()->addSeconds(30), function (): array {
            $bars = DB::table('instruments as instrument')
                ->join('price_bars as bar', 'bar.instrument_id', '=', 'instrument.id')
                ->whereIn('instrument.symbol', array_values(self::INDEX_SYMBOLS))
                ->whereIn('bar.interval', ['1m', '1d'])
                ->where('bar.bar_time', '>=', now()->subDays(35))
                ->orderBy('instrument.symbol')
                ->orderByDesc('bar.bar_time')
                ->get([
                    'instrument.symbol', 'instrument.currency', 'bar.interval',
                    'bar.bar_time', 'bar.open', 'bar.high', 'bar.low', 'bar.close',
                ])
                ->groupBy('symbol');

            return collect(self::INDEX_SYMBOLS)->mapWithKeys(function (string $symbol) use ($bars): array {
                $symbolBars = $bars->get($symbol, collect());
                $latest = $symbolBars->first();
                $price = $latest && is_numeric($latest->close) ? (float) $latest->close : null;
                $latestDay = $latest ? Carbon::parse($latest->bar_time)->toDateString() : null;
                $previousDaily = $symbolBars
                    ->first(fn (object $bar): bool => $bar->interval === '1d'
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
                        'x' => Carbon::parse($bar->bar_time)->getTimestampMs(),
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

    /**
     * The full index list (name/symbol/price/currency/change/candles), the
     * same shape MarketData::$markets carries before market situations are
     * merged in.
     *
     * @return list<array{name: string, symbol: string, price: ?float, currency: string, change: ?float, candles: array}>
     */
    public function indexMarkets(): array
    {
        $databaseMarkets = $this->rawIndexMarkets();

        return collect(self::INDEX_SYMBOLS)
            ->map(function (string $symbol, string $name) use ($databaseMarkets): array {
                $market = $databaseMarkets[$symbol] ?? [];

                return [
                    'name' => $name,
                    'symbol' => $symbol,
                    'price' => $market['price'] ?? null,
                    'currency' => $market['currency'] ?? '',
                    'change' => $market['change'] ?? null,
                    'candles' => $market['candles'] ?? [],
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function macroCards(): array
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
            $daxLevels = $daxLevels->filter(fn (object $row): bool => is_numeric($row->close)
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
                'value' => (float) (AiScore::toTen($point->score) ?? 0),
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
            for ($index = 1; $index < $prices->count(); $index++) {
                $returns->push($prices[$index - 1] > 0 ? ($prices[$index] / $prices[$index - 1]) - 1 : null);
            }
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
}
