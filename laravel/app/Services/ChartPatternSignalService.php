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
    private const CACHE_KEY = 'dashboard.chart-pattern-signals.v3';

    public function recentEvents(): Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function (): Collection {
            $events = $this->detect();
            $events = $this->attachProbabilities($events);

            return $this->attachSparklines($events);
        });
    }

    /**
     * Attaches each event's historical "does the price rise over the
     * following 20 trading days" statistic from chartview_signal_statistics
     * - a 3-year backtest across all detected occurrences of that event
     * type, refreshed daily by chartview:refresh-signals. That command's own
     * *recent-event detection* is what's stalled on technical_indicators
     * (see class docblock); the long-window backtest it also (re)computes
     * every run is unaffected and safe to reuse here.
     */
    private function attachProbabilities(Collection $events): Collection
    {
        if ($events->isEmpty()) {
            return $events;
        }

        $statistics = DB::table('chartview_signal_statistics')
            ->get(['event_key', 'rise_probability', 'average_return', 'sample_size'])
            ->keyBy('event_key');

        return $events->map(function (array $event) use ($statistics): array {
            $stat = $statistics->get($event['event_key']);

            $event['rise_probability_20d'] = $stat && is_numeric($stat->rise_probability) ? round((float) $stat->rise_probability, 1) : null;
            $event['average_return_20d'] = $stat && is_numeric($stat->average_return) ? round((float) $stat->average_return, 1) : null;
            $event['probability_sample_size'] = $stat ? (int) $stat->sample_size : null;

            return $event;
        });
    }

    /** Attaches a normalized SVG polyline (viewBox 0 0 100 32) of each event's last ~30 daily closes, so the pattern itself is visible, not just its label. */
    private function attachSparklines(Collection $events): Collection
    {
        if ($events->isEmpty()) {
            return $events;
        }

        $eventDate = $events->first()['time'];
        $instrumentIds = $events->pluck('instrument_id')->unique()->values();

        $seriesByInstrument = DB::table('price_bars')
            ->whereIn('instrument_id', $instrumentIds)
            ->where('interval', '1d')
            ->whereBetween('bar_time', [Carbon::parse($eventDate)->subDays(60), $eventDate])
            ->orderBy('bar_time')
            ->get(['instrument_id', 'close', 'adjusted_close'])
            ->groupBy('instrument_id');

        return $events->map(function (array $event) use ($seriesByInstrument): array {
            $closes = ($seriesByInstrument->get($event['instrument_id']) ?? collect())
                ->map(fn (object $bar) => (float) ($bar->adjusted_close ?? $bar->close))
                ->slice(-30)
                ->values();

            $event['sparkline'] = $closes->count() >= 2 ? $this->sparkline($closes->all()) : null;

            return $event;
        });
    }

    /** @param list<float> $closes */
    private function sparkline(array $closes): string
    {
        $min = min($closes);
        $max = max($closes);
        $range = $max - $min;
        $count = count($closes);

        return collect($closes)->map(function (float $close, int $i) use ($min, $range, $count): string {
            $x = $count > 1 ? ($i / ($count - 1)) * 100 : 0;
            $y = $range > 0 ? 32 - (($close - $min) / $range) * 32 : 16;

            return round($x, 1).','.round($y, 1);
        })->implode(' ');
    }

    private function detect(): Collection
    {
        $rows = DB::select(<<<'SQL'
            WITH bars AS (
                SELECT pb.instrument_id, pb.bar_time, pb.open, pb.high, pb.low,
                       COALESCE(pb.adjusted_close, pb.close) AS close,
                       LAG(COALESCE(pb.adjusted_close, pb.close)) OVER w AS previous_close,
                       LAG(pb.open) OVER w AS previous_open,
                       MAX(pb.high) OVER (PARTITION BY pb.instrument_id ORDER BY pb.bar_time ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) AS prior_20_high,
                       MIN(pb.low) OVER (PARTITION BY pb.instrument_id ORDER BY pb.bar_time ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) AS prior_20_low,
                       AVG(COALESCE(pb.adjusted_close, pb.close)) OVER (PARTITION BY pb.instrument_id ORDER BY pb.bar_time ROWS BETWEEN 49 PRECEDING AND CURRENT ROW) AS sma_50,
                       AVG(COALESCE(pb.adjusted_close, pb.close)) OVER (PARTITION BY pb.instrument_id ORDER BY pb.bar_time ROWS BETWEEN 199 PRECEDING AND CURRENT ROW) AS sma_200,
                       AVG(COALESCE(pb.adjusted_close, pb.close)) OVER (PARTITION BY pb.instrument_id ORDER BY pb.bar_time ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) AS sma_20,
                       STDDEV_SAMP(COALESCE(pb.adjusted_close, pb.close)) OVER (PARTITION BY pb.instrument_id ORDER BY pb.bar_time ROWS BETWEEN 19 PRECEDING AND CURRENT ROW) AS stddev_20,
                       ROW_NUMBER() OVER w AS row_number
                FROM price_bars pb
                JOIN instruments i ON i.id = pb.instrument_id
                WHERE pb.interval = '1d' AND pb.bar_time >= CURRENT_DATE - INTERVAL '400 days'
                  AND i.type = 'stock' AND i.is_active = TRUE AND i.deleted_at IS NULL
                WINDOW w AS (PARTITION BY pb.instrument_id ORDER BY pb.bar_time)
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
            SELECT s.instrument_id, i.symbol, i.name, s.bar_time, s.close, s.previous_close,
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
            'time' => $row->bar_time,
            'event_key' => $row->event_key,
            'label' => $row->label_de,
            'tone' => $row->tone,
            'change_pct' => is_numeric($row->close) && is_numeric($row->previous_close) && (float) $row->previous_close !== 0.0
                ? ((float) $row->close / (float) $row->previous_close - 1) * 100
                : null,
        ]);
    }
}
