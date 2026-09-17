<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Detects classic chart patterns and indicator transitions (golden/death
 * cross, SMA/Bollinger breaks, RSI extremes, candlestick patterns, 20-day
 * breakouts) directly from price_bars for the most recent trading day
 * (~last 24h).
 *
 * technical_indicators - the table chartview:refresh-signals reads for the
 * same kind of detection - is fed by an external pipeline that stalled
 * weeks ago (last bar_time 2026-08-26 as of 2026-09-17), while price_bars
 * itself is kept current daily. Recomputing SMA/RSI/Bollinger in-query from
 * price_bars sidesteps that stalled dependency instead of surfacing weeks-
 * old "recent" events.
 */
final class ChartPatternSignalService
{
    private const CACHE_KEY = 'dashboard.chart-pattern-signals.v11';

    /** Event types driven by a bounded (0-100) oscillator, shown as its own panel below the candles rather than overlaid on price. */
    private const INDICATOR_EVENT_KEYS = ['rsi_oversold', 'rsi_overbought'];

    /** Moving-average event types and which SMA periods to draw directly on the price chart - they share the candles' own price scale, unlike RSI. */
    private const SMA_OVERLAY_EVENT_KEYS = [
        'golden_cross' => [50, 200],
        'death_cross' => [50, 200],
        'price_above_sma50' => [50],
        'price_below_sma50' => [50],
    ];

    public function recentEvents(): Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function (): Collection {
            $events = $this->detect();
            $events = $this->attachProbabilities($events);

            return $this->attachCharts($events);
        });
    }

    /**
     * Attaches each event's historical "does the price rise over the
     * following 20 trading days" probability, and whether this exact
     * event/stock constellation has happened before for this specific
     * instrument - not just the same event type across all stocks.
     *
     * Both come from chartview_signal_statistics (global, per event_key) and
     * chartview_instrument_signal_statistics (per instrument_id+event_key) -
     * a 3-year backtest chartview:refresh-signals (re)computes daily. That
     * command's own *recent-event detection* is what's stalled on
     * technical_indicators (see class docblock); the long-window backtest it
     * also recomputes every run is unaffected and safe to reuse here.
     *
     * The shown probability blends instrument-specific and global evidence
     * the same way chartview_signal_events already does: too few own
     * occurrences (<10) falls back to the global rate entirely, >=30 uses
     * the instrument's own rate, and in between blends both weighted by
     * sample size - avoiding a false-precision "73%" off 2 past trades.
     */
    private function attachProbabilities(Collection $events): Collection
    {
        if ($events->isEmpty()) {
            return $events;
        }

        $global = DB::table('chartview_signal_statistics')
            ->get(['event_key', 'rise_probability', 'average_return', 'sample_size'])
            ->keyBy('event_key');

        $instrumentIds = $events->pluck('instrument_id')->unique()->values();
        $perInstrument = DB::table('chartview_instrument_signal_statistics')
            ->whereIn('instrument_id', $instrumentIds)
            ->get(['instrument_id', 'event_key', 'rise_probability', 'average_return', 'sample_size'])
            ->keyBy(fn (object $row): string => $row->instrument_id.':'.$row->event_key);

        return $events->map(function (array $event) use ($global, $perInstrument): array {
            $globalStat = $global->get($event['event_key']);
            $globalProbability = $globalStat && is_numeric($globalStat->rise_probability) ? (float) $globalStat->rise_probability : null;

            $ownStat = $perInstrument->get($event['instrument_id'].':'.$event['event_key']);
            $ownSampleSize = $ownStat ? (int) $ownStat->sample_size : 0;
            $ownProbability = $ownStat && is_numeric($ownStat->rise_probability) ? (float) $ownStat->rise_probability : null;

            if ($ownSampleSize < 10 || $ownProbability === null || $globalProbability === null) {
                $blended = $globalProbability;
                $scope = 'global';
                $sampleSize = $globalStat ? (int) $globalStat->sample_size : null;
            } else {
                $weight = $ownSampleSize / ($ownSampleSize + 20);
                $blended = $ownProbability * $weight + $globalProbability * (1 - $weight);
                $scope = $ownSampleSize < 30 ? 'blended' : 'instrument';
                $sampleSize = $ownSampleSize;
            }

            $event['rise_probability_20d'] = $blended !== null ? round($blended, 1) : null;
            $event['probability_scope'] = $scope;
            $event['probability_sample_size'] = $sampleSize;
            $event['average_return_20d'] = $globalStat && is_numeric($globalStat->average_return) ? round((float) $globalStat->average_return, 1) : null;
            // Has this exact event type already happened for this specific
            // stock before, independent of the blending threshold above.
            $event['instrument_occurrence_count'] = $ownSampleSize;

            return $event;
        });
    }

    /** Trading days shown in the candlestick chart and RSI panel. */
    private const DISPLAY_DAYS = 20;

    /**
     * Attaches a 20-day OHLC candlestick chart (viewBox 0 0 100 50) built
     * straight from price_bars, so the actual chart pattern is visible, not
     * just its label - plus, for RSI-driven events, a second 0-100 panel
     * plotting the RSI-14 series underneath, the same way a real charting
     * tool keeps a bounded oscillator in its own panel instead of squashing
     * it onto the price axis.
     */
    private function attachCharts(Collection $events): Collection
    {
        if ($events->isEmpty()) {
            return $events;
        }

        $eventDate = $events->first()['time'];
        $instrumentIds = $events->pluck('instrument_id')->unique()->values();

        // Some instruments carry two parallel price_bars feeds that disagree
        // wildly (seen: a "twelve_data" series around ~11 next to a
        // "twelvedata|fallback:yfinance" series around ~85 for the same
        // stock) - and because their bar_time values can straddle midnight
        // UTC by a couple of hours, they land on *adjacent* calendar dates
        // rather than colliding on the same one, so the per-day DISTINCT ON
        // below doesn't catch it: it alternates in and out of the window as
        // a zigzag instead of a clean duplicate. Pinning every fetch to each
        // instrument's single dominant source (by row count, over the widest
        // window used below) avoids mixing the two price scales.
        $dominantSources = $this->dominantSourceByInstrument($instrumentIds, Carbon::parse($eventDate)->subDays(400), Carbon::parse($eventDate));

        // 90 calendar days (~60 trading days) covers the 20 displayed bars
        // plus the 14-bar RSI seed with comfortable headroom for weekends/
        // holidays. DISTINCT ON collapses any instrument+day with more than
        // one interval='1d' snapshot from the same source down to the latest
        // one - without it, some instruments show doubled-looking candles
        // for the same calendar day.
        $barsByInstrument = DB::table('price_bars')
            ->selectRaw('DISTINCT ON (instrument_id, bar_time::date) instrument_id, bar_time, open, high, low, close')
            ->whereIn('instrument_id', $instrumentIds)
            ->where('interval', '1d')
            ->whereBetween('bar_time', [Carbon::parse($eventDate)->subDays(90), $eventDate])
            ->where(fn ($query) => $this->constrainToDominantSource($query, $dominantSources))
            ->orderByRaw('instrument_id, bar_time::date, bar_time DESC')
            ->get()
            ->groupBy('instrument_id');

        // SMA 200 needs 200 preceding closes just to seed the first point of
        // the 20-day display window - a much wider lookback than the 90 days
        // above, so it's only fetched for the instruments that actually need
        // an overlay (golden/death cross, price vs. SMA 50).
        $smaInstrumentIds = $events
            ->filter(fn (array $event): bool => isset(self::SMA_OVERLAY_EVENT_KEYS[$event['event_key']]))
            ->pluck('instrument_id')->unique()->values();
        $smaClosesByInstrument = $smaInstrumentIds->isEmpty() ? collect() : DB::table('price_bars')
            ->selectRaw('DISTINCT ON (instrument_id, bar_time::date) instrument_id, bar_time, close')
            ->whereIn('instrument_id', $smaInstrumentIds)
            ->where('interval', '1d')
            ->whereBetween('bar_time', [Carbon::parse($eventDate)->subDays(400), $eventDate])
            ->where(fn ($query) => $this->constrainToDominantSource($query, $dominantSources))
            ->orderByRaw('instrument_id, bar_time::date, bar_time DESC')
            ->get()
            ->groupBy('instrument_id');

        return $events->map(function (array $event) use ($barsByInstrument, $smaClosesByInstrument): array {
            $bars = $barsByInstrument->get($event['instrument_id']) ?? collect();
            $window = $bars->slice(-self::DISPLAY_DAYS)->values();

            if ($window->count() >= 2) {
                $low = $window->min(fn (object $bar) => (float) $bar->low);
                $high = $window->max(fn (object $bar) => (float) $bar->high);
                // A breakout level (support/resistance) can sit outside the
                // visible 20-day high/low - e.g. a 20-day-high breakout is
                // by definition above every one of those 20 highs - so the
                // scale has to stretch to include it, or the line would be
                // drawn off the top/bottom edge of the chart.
                if ($event['breakout_level']) {
                    $low = min($low, $event['breakout_level']['value']);
                    $high = max($high, $event['breakout_level']['value']);
                }

                $periods = self::SMA_OVERLAY_EVENT_KEYS[$event['event_key']] ?? null;
                $displaySmaSeries = [];
                if ($periods) {
                    $closes = ($smaClosesByInstrument->get($event['instrument_id']) ?? collect())
                        ->pluck('close')->map(fn ($v) => (float) $v)->values()->all();
                    foreach ($periods as $period) {
                        $displaySma = array_slice($this->smaSeries($closes, $period), -$window->count());
                        foreach ($displaySma as $value) {
                            if ($value !== null) {
                                $low = min($low, $value);
                                $high = max($high, $value);
                            }
                        }
                        $displaySmaSeries[$period] = $displaySma;
                    }
                }

                $event['candles'] = $this->candles($window, $low, $high);
                $event['breakout_line_y'] = $event['breakout_level']
                    ? $this->priceToY($event['breakout_level']['value'], $low, $high)
                    : null;
                $event['overlays'] = $this->overlaySeries($displaySmaSeries, $window->count(), $low, $high);
            } else {
                $event['candles'] = [];
                $event['breakout_line_y'] = null;
                $event['overlays'] = [];
            }

            $event['indicator_series'] = in_array($event['event_key'], self::INDICATOR_EVENT_KEYS, true)
                ? $this->rsiPanel($bars)
                : null;

            $span = self::CANDLESTICK_PATTERN_SPANS[$event['event_key']] ?? null;
            $event['pattern_range'] = $span !== null && count($event['candles']) >= $span
                ? $this->patternRange($event['candles'], $span)
                : null;

            return $event;
        });
    }

    /** @return Collection<int, string> source keyed by instrument_id, the one with the most rows in the window */
    private function dominantSourceByInstrument(Collection $instrumentIds, Carbon $since, Carbon $until): Collection
    {
        if ($instrumentIds->isEmpty()) {
            return collect();
        }

        return DB::table('price_bars')
            ->select('instrument_id', 'source', DB::raw('COUNT(*) as n'))
            ->whereIn('instrument_id', $instrumentIds)
            ->where('interval', '1d')
            ->whereBetween('bar_time', [$since, $until])
            ->groupBy('instrument_id', 'source')
            ->get()
            ->groupBy('instrument_id')
            ->map(fn (Collection $rows) => $rows->sortByDesc('n')->first()->source);
    }

    /** @param  \Illuminate\Database\Query\Builder  $query  @param  Collection<int, string>  $dominantSources */
    private function constrainToDominantSource($query, Collection $dominantSources): void
    {
        foreach ($dominantSources as $instrumentId => $source) {
            $query->orWhere(fn ($nested) => $nested->where('instrument_id', $instrumentId)->where('source', $source));
        }
    }

    /** Colors chosen to stay visually distinct from candles (emerald/rose), the amber pattern markers and the rose/emerald breakout line. */
    private const OVERLAY_COLORS = [50 => ['label' => 'SMA 50', 'color' => 'sky'], 200 => ['label' => 'SMA 200', 'color' => 'violet']];

    /**
     * @param  array<int, list<float|null>>  $displaySmaSeriesByPeriod
     * @return list<array{label: string, color: string, points: string}>
     */
    private function overlaySeries(array $displaySmaSeriesByPeriod, int $count, float $low, float $high): array
    {
        $overlays = [];
        foreach ($displaySmaSeriesByPeriod as $period => $series) {
            $points = [];
            foreach ($series as $i => $value) {
                if ($value === null) {
                    continue;
                }
                $x = $count > 1 ? (($i + 0.5) / $count) * 100 : 50;
                $points[] = round($x, 2).','.$this->priceToY($value, $low, $high);
            }
            if ($points !== []) {
                $overlays[] = [...self::OVERLAY_COLORS[$period], 'points' => implode(' ', $points)];
            }
        }

        return $overlays;
    }

    /** @param list<float> $closes @return list<float|null> simple moving average aligned to $closes - the first period-1 entries are null (not enough history yet to seed them). */
    private function smaSeries(array $closes, int $period): array
    {
        $series = array_fill(0, count($closes), null);
        for ($i = $period - 1; $i < count($closes); $i++) {
            $series[$i] = array_sum(array_slice($closes, $i - $period + 1, $period)) / $period;
        }

        return $series;
    }

    /**
     * The bounding candle span each candlestick pattern is actually defined
     * over - an engulfing pattern is a relationship between two consecutive
     * candles, a pin bar is a single candle's shape - marked in the chart as
     * start/end guide lines instead of leaving the reader to guess which
     * candle(s) the label refers to.
     */
    private const CANDLESTICK_PATTERN_SPANS = [
        'pattern_bullish_engulfing' => 2,
        'pattern_bearish_engulfing' => 2,
        'pattern_bullish_pin_bar' => 1,
        'pattern_bearish_pin_bar' => 1,
    ];

    /** @param list<array{x: float, width: float}> $candles */
    private function patternRange(array $candles, int $span): array
    {
        $slice = array_slice($candles, -$span);
        $first = $slice[0];
        $last = end($slice);

        return [
            'start_x' => round($first['x'] - $first['width'] / 2, 2),
            'end_x' => round($last['x'] + $last['width'] / 2, 2),
        ];
    }

    /** Candlestick chart viewBox height - see the class docblock on why this needed to grow from the original 32: too short and a whole day's wick collapses to a barely-visible sliver. */
    private const CANDLE_CHART_HEIGHT = 50;

    /** @return list<array{x: float, width: float, high_y: float, low_y: float, body_y: float, body_height: float, bullish: bool}> */
    private function candles(Collection $bars, float $min, float $max): array
    {
        $range = $max - $min;
        $count = $bars->count();
        $slot = 100 / $count;
        $height = self::CANDLE_CHART_HEIGHT;
        $y = fn (float $value): float => $range > 0 ? $height - (($value - $min) / $range) * $height : $height / 2;

        return $bars->map(function (object $bar, int $i) use ($slot, $y): array {
            $open = (float) $bar->open;
            $close = (float) $bar->close;
            $bodyTop = $y(max($open, $close));
            $bodyBottom = $y(min($open, $close));

            return [
                'x' => round(($i + 0.5) * $slot, 2),
                'width' => round($slot * 0.6, 2),
                'high_y' => round($y((float) $bar->high), 2),
                'low_y' => round($y((float) $bar->low), 2),
                'body_y' => round($bodyTop, 2),
                // A doji (open == close) would otherwise draw a zero-height,
                // invisible body - keep a thin sliver visible instead.
                'body_height' => round(max($bodyBottom - $bodyTop, 0.6), 2),
                'bullish' => $close >= $open,
            ];
        })->values()->all();
    }

    /** Converts a raw price into the candle chart's y-coordinate, using the same min/max scale candles() was called with. */
    private function priceToY(float $value, float $min, float $max): float
    {
        $range = $max - $min;
        $height = self::CANDLE_CHART_HEIGHT;

        return round($range > 0 ? $height - (($value - $min) / $range) * $height : $height / 2, 2);
    }

    /** RSI panel viewBox height - kept smaller than CANDLE_CHART_HEIGHT since it is a secondary panel, but tall enough (with the matching viewBox in the blade view) to read as a real line, not a sliver. */
    private const RSI_PANEL_HEIGHT = 25;

    /** @return array{label: string, points: string, overbought_y: float, oversold_y: float}|null */
    private function rsiPanel(Collection $bars): ?array
    {
        $closes = $bars->pluck('close')->map(fn ($v) => (float) $v)->values()->all();
        $series = $this->rsiSeries($closes, 14);
        $displaySeries = array_slice($series, -min(self::DISPLAY_DAYS, count($closes)));
        $count = count($displaySeries);
        $height = self::RSI_PANEL_HEIGHT;

        $points = [];
        foreach ($displaySeries as $i => $value) {
            if ($value === null) {
                continue;
            }
            $x = $count > 1 ? ($i / ($count - 1)) * 100 : 0;
            $points[] = round($x, 1).','.round($height - ($value / 100) * $height, 1);
        }

        if ($points === []) {
            return null;
        }

        return [
            'label' => __('RSI (14)'),
            'points' => implode(' ', $points),
            'overbought_y' => round($height - (70 / 100) * $height, 1),
            'oversold_y' => round($height - (30 / 100) * $height, 1),
        ];
    }

    /**
     * Wilder's smoothed RSI as a full series aligned to $closes (first
     * $period entries are null - not enough history yet to seed them).
     *
     * @param  list<float>  $closes
     * @return list<float|null>
     */
    private function rsiSeries(array $closes, int $period): array
    {
        $series = array_fill(0, count($closes), null);
        if (count($closes) <= $period) {
            return $series;
        }

        $gains = 0.0;
        $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $delta = $closes[$i] - $closes[$i - 1];
            $delta >= 0 ? $gains += $delta : $losses -= $delta;
        }
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;
        $series[$period] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + $avgGain / $avgLoss));

        for ($i = $period + 1; $i < count($closes); $i++) {
            $delta = $closes[$i] - $closes[$i - 1];
            $avgGain = ($avgGain * ($period - 1) + max($delta, 0.0)) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + max(-$delta, 0.0)) / $period;
            $series[$i] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + $avgGain / $avgLoss));
        }

        return $series;
    }

    private function detect(): Collection
    {
        $rows = DB::select(<<<'SQL'
            WITH source_counts AS (
                -- Some instruments carry two parallel price_bars feeds that
                -- disagree wildly (seen: a "twelve_data" series around ~11
                -- next to a "twelvedata|fallback:yfinance" series around ~85
                -- for the same stock) - and because their bar_time values can
                -- straddle midnight UTC by a couple of hours, they land on
                -- *adjacent* calendar dates rather than colliding on the same
                -- one, so a per-day DISTINCT ON alone doesn't catch it. Pin
                -- every instrument to its single dominant source (by row
                -- count) before doing anything else.
                SELECT pb.instrument_id, pb.source, COUNT(*) AS n
                FROM price_bars pb
                WHERE pb.interval = '1d' AND pb.bar_time >= CURRENT_DATE - INTERVAL '400 days'
                GROUP BY pb.instrument_id, pb.source
            ), dominant_source AS (
                SELECT DISTINCT ON (instrument_id) instrument_id, source
                FROM source_counts
                ORDER BY instrument_id, n DESC
            ), daily_bars AS (
                -- price_bars can also hold more than one interval='1d' row
                -- per calendar day from the *same* source (repeated intraday
                -- snapshots at different bar_time values, e.g. 00:00/04:00/
                -- 22:00 UTC) - without collapsing to one row per day too,
                -- every window function below treats those as separate
                -- trading days, corrupting SMA/RSI/Bollinger and producing
                -- doubled-looking candles later. Keep the latest snapshot of
                -- each day as the most complete one.
                SELECT DISTINCT ON (pb.instrument_id, pb.bar_time::date)
                       pb.instrument_id, pb.bar_time, pb.open, pb.high, pb.low, pb.close, pb.adjusted_close
                FROM price_bars pb
                JOIN dominant_source ds ON ds.instrument_id = pb.instrument_id AND ds.source = pb.source
                WHERE pb.interval = '1d' AND pb.bar_time >= CURRENT_DATE - INTERVAL '400 days'
                ORDER BY pb.instrument_id, pb.bar_time::date, pb.bar_time DESC
            ), bars AS (
                SELECT db.instrument_id, db.bar_time, db.open, db.high, db.low,
                       COALESCE(db.adjusted_close, db.close) AS close,
                       LAG(COALESCE(db.adjusted_close, db.close)) OVER w AS previous_close,
                       LAG(db.open) OVER w AS previous_open,
                       MAX(db.high) OVER (PARTITION BY db.instrument_id ORDER BY db.bar_time ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) AS prior_20_high,
                       MIN(db.low) OVER (PARTITION BY db.instrument_id ORDER BY db.bar_time ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) AS prior_20_low,
                       AVG(COALESCE(db.adjusted_close, db.close)) OVER (PARTITION BY db.instrument_id ORDER BY db.bar_time ROWS BETWEEN 49 PRECEDING AND CURRENT ROW) AS sma_50,
                       AVG(COALESCE(db.adjusted_close, db.close)) OVER (PARTITION BY db.instrument_id ORDER BY db.bar_time ROWS BETWEEN 199 PRECEDING AND CURRENT ROW) AS sma_200,
                       AVG(COALESCE(db.adjusted_close, db.close)) OVER (PARTITION BY db.instrument_id ORDER BY db.bar_time ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) AS sma_20,
                       STDDEV_SAMP(COALESCE(db.adjusted_close, db.close)) OVER (PARTITION BY db.instrument_id ORDER BY db.bar_time ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) AS stddev_20,
                       ROW_NUMBER() OVER w AS row_number
                FROM daily_bars db
                JOIN instruments i ON i.id = db.instrument_id
                WHERE i.type = 'stock' AND i.is_active = TRUE AND i.deleted_at IS NULL
                WINDOW w AS (PARTITION BY db.instrument_id ORDER BY db.bar_time)
            ), indicators AS (
                SELECT bars.*,
                       sma_20 + (2 * stddev_20) AS bollinger_upper,
                       sma_20 - (2 * stddev_20) AS bollinger_lower,
                       100 - (100 / (1 + (
                           AVG(GREATEST(close - previous_close, 0)) OVER (PARTITION BY instrument_id ORDER BY bar_time ROWS BETWEEN 13 PRECEDING AND CURRENT ROW)
                           / NULLIF(AVG(GREATEST(previous_close - close, 0)) OVER (PARTITION BY instrument_id ORDER BY bar_time ROWS BETWEEN 13 PRECEDING AND CURRENT ROW), 0)
                       ))) AS rsi_14
                FROM bars
            ), series AS (
                SELECT indicators.*,
                       LAG(sma_50) OVER w AS previous_sma_50,
                       LAG(sma_200) OVER w AS previous_sma_200,
                       LAG(rsi_14) OVER w AS previous_rsi,
                       LAG(bollinger_upper) OVER w AS previous_bollinger_upper,
                       LAG(bollinger_lower) OVER w AS previous_bollinger_lower
                FROM indicators
                WINDOW w AS (PARTITION BY instrument_id ORDER BY bar_time)
            ), recent_days AS (
                -- Daily bars only ever advance once per trading day, so "the
                -- last 24h" of events is just the single most recent
                -- bar_time - not a rolling now()-24h window, which would
                -- intermittently see zero rows depending on time of day.
                SELECT DISTINCT bar_time FROM series ORDER BY bar_time DESC LIMIT 1
            )
            SELECT s.instrument_id, i.symbol, i.name, i.country, s.bar_time, s.close, s.previous_close,
                   s.bollinger_upper, s.bollinger_lower, s.prior_20_high, s.prior_20_low,
                   event.event_key, event.label_de, event.tone
            FROM series s
            JOIN instruments i ON i.id = s.instrument_id
            CROSS JOIN LATERAL (VALUES
                ('golden_cross', 'Golden Cross: SMA 50 über SMA 200', 'positive', s.row_number >= 200 AND s.previous_sma_50 <= s.previous_sma_200 AND s.sma_50 > s.sma_200),
                ('death_cross', 'Death Cross: SMA 50 unter SMA 200', 'negative', s.row_number >= 200 AND s.previous_sma_50 >= s.previous_sma_200 AND s.sma_50 < s.sma_200),
                ('price_above_sma50', 'Kurs über SMA 50', 'positive', s.row_number >= 50 AND s.previous_close <= s.previous_sma_50 AND s.close > s.sma_50),
                ('price_below_sma50', 'Kurs unter SMA 50', 'negative', s.row_number >= 50 AND s.previous_close >= s.previous_sma_50 AND s.close < s.sma_50),
                ('rsi_oversold', 'RSI überverkauft', 'positive', s.previous_rsi >= 30 AND s.rsi_14 < 30),
                ('rsi_overbought', 'RSI überkauft', 'negative', s.previous_rsi <= 70 AND s.rsi_14 > 70),
                ('resistance_breakout', 'Widerstand überschritten', 'positive', s.previous_close <= s.previous_bollinger_upper AND s.close > s.bollinger_upper),
                ('support_breakdown', 'Unterstützung unterschritten', 'negative', s.previous_close >= s.previous_bollinger_lower AND s.close < s.bollinger_lower),
                ('pattern_bullish_engulfing', 'Chartmuster: Bullish Engulfing', 'positive', s.close > s.open AND s.previous_close < s.previous_open AND s.open <= s.previous_close AND s.close >= s.previous_open),
                ('pattern_bearish_engulfing', 'Chartmuster: Bearish Engulfing', 'negative', s.close < s.open AND s.previous_close > s.previous_open AND s.open >= s.previous_close AND s.close <= s.previous_open),
                ('pattern_bullish_pin_bar', 'Chartmuster: Bullish Pin Bar', 'positive', (s.high - s.low) > 0 AND (LEAST(s.open, s.close) - s.low) >= 2 * GREATEST(ABS(s.close - s.open), (s.high - s.low) * .05) AND (s.high - GREATEST(s.open, s.close)) <= ABS(s.close - s.open)),
                ('pattern_bearish_pin_bar', 'Chartmuster: Bearish Pin Bar', 'negative', (s.high - s.low) > 0 AND (s.high - GREATEST(s.open, s.close)) >= 2 * GREATEST(ABS(s.close - s.open), (s.high - s.low) * .05) AND (LEAST(s.open, s.close) - s.low) <= ABS(s.close - s.open)),
                ('pattern_upside_breakout', 'Chartmuster: 20-Tage-Ausbruch nach oben', 'positive', s.close > s.prior_20_high),
                ('pattern_downside_breakout', 'Chartmuster: 20-Tage-Ausbruch nach unten', 'negative', s.close < s.prior_20_low)
            ) AS event(event_key, label_de, tone, triggered)
            WHERE event.triggered AND s.bar_time IN (SELECT bar_time FROM recent_days)
            ORDER BY s.bar_time DESC, i.symbol
        SQL);

        return collect($rows)->map(fn (object $row): array => [
            'instrument_id' => (int) $row->instrument_id,
            'symbol' => $row->symbol,
            'name' => $row->name,
            'country' => strtoupper((string) $row->country),
            'time' => $row->bar_time,
            'event_key' => $row->event_key,
            'label' => $row->label_de,
            'tone' => $row->tone,
            'change_pct' => is_numeric($row->close) && is_numeric($row->previous_close) && (float) $row->previous_close !== 0.0
                ? ((float) $row->close / (float) $row->previous_close - 1) * 100
                : null,
            'breakout_level' => $this->breakoutLevel($row),
        ]);
    }

    /**
     * The specific price level a breakout event actually broke through - the
     * Bollinger band for the band-based events, the prior 20-day high/low
     * for the breakout-pattern events - so the chart can draw the line that
     * was crossed instead of leaving the reader to infer it from the label.
     */
    private function breakoutLevel(object $row): ?array
    {
        return match ($row->event_key) {
            'resistance_breakout' => is_numeric($row->bollinger_upper)
                ? ['type' => 'resistance', 'value' => (float) $row->bollinger_upper] : null,
            'support_breakdown' => is_numeric($row->bollinger_lower)
                ? ['type' => 'support', 'value' => (float) $row->bollinger_lower] : null,
            'pattern_upside_breakout' => is_numeric($row->prior_20_high)
                ? ['type' => 'resistance', 'value' => (float) $row->prior_20_high] : null,
            'pattern_downside_breakout' => is_numeric($row->prior_20_low)
                ? ['type' => 'support', 'value' => (float) $row->prior_20_low] : null,
            default => null,
        };
    }
}
