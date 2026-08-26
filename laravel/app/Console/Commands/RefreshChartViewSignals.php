<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RefreshChartViewSignals extends Command
{
    protected $signature = 'chartview:refresh-signals';
    protected $description = 'Recalculate global and stock-specific ChartView probabilities and retain three trading days';

    public function handle(): int
    {
        DB::transaction(function (): void {
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE chartview_detected_events ON COMMIT DROP AS
                WITH series AS (
                    SELECT ti.instrument_id, ti.bar_time, pb.open, pb.high, pb.low, fs.close,
                           ti.sma_50, ti.sma_200, ti.macd, ti.macd_signal, ti.rsi_14,
                           ti.bollinger_upper, ti.bollinger_lower,
                           LAG(fs.close) OVER w AS previous_close, LAG(pb.open) OVER w AS previous_open,
                           LAG(ti.sma_50) OVER w AS previous_sma_50, LAG(ti.sma_200) OVER w AS previous_sma_200,
                           LAG(ti.macd) OVER w AS previous_macd, LAG(ti.macd_signal) OVER w AS previous_macd_signal,
                           LAG(ti.rsi_14) OVER w AS previous_rsi,
                           LAG(ti.bollinger_upper) OVER w AS previous_bollinger_upper,
                           LAG(ti.bollinger_lower) OVER w AS previous_bollinger_lower,
                           MAX(pb.high) OVER (PARTITION BY ti.instrument_id ORDER BY ti.bar_time, ti.id ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) AS prior_20_high,
                           MIN(pb.low) OVER (PARTITION BY ti.instrument_id ORDER BY ti.bar_time, ti.id ROWS BETWEEN 20 PRECEDING AND 1 PRECEDING) AS prior_20_low,
                           LEAD(fs.close, 20) OVER w AS forward_close
                    FROM technical_indicators ti
                    JOIN feature_store fs ON fs.instrument_id = ti.instrument_id AND fs.interval = ti.interval AND fs.bar_time = ti.bar_time
                    JOIN price_bars pb ON pb.instrument_id = ti.instrument_id AND pb.interval = ti.interval AND pb.bar_time = ti.bar_time
                    JOIN instruments i ON i.id = ti.instrument_id
                    WHERE ti.interval = '1d' AND ti.bar_time >= CURRENT_DATE - INTERVAL '3 years 45 days'
                      AND i.type = 'stock' AND i.is_active = TRUE AND i.deleted_at IS NULL
                    WINDOW w AS (PARTITION BY ti.instrument_id ORDER BY ti.bar_time, ti.id)
                )
                SELECT s.instrument_id, s.bar_time, s.close, s.forward_close,
                       event.event_key, event.label_de, event.label_en, event.tone
                FROM series s CROSS JOIN LATERAL (VALUES
                    ('golden_cross', 'Golden Cross: SMA 50 über SMA 200', 'Golden Cross: SMA 50 above SMA 200', 'positive', s.previous_sma_50 <= s.previous_sma_200 AND s.sma_50 > s.sma_200),
                    ('death_cross', 'Death Cross: SMA 50 unter SMA 200', 'Death Cross: SMA 50 below SMA 200', 'negative', s.previous_sma_50 >= s.previous_sma_200 AND s.sma_50 < s.sma_200),
                    ('price_above_sma50', 'Kurs über SMA 50', 'Price above SMA 50', 'positive', s.previous_close <= s.previous_sma_50 AND s.close > s.sma_50),
                    ('price_below_sma50', 'Kurs unter SMA 50', 'Price below SMA 50', 'negative', s.previous_close >= s.previous_sma_50 AND s.close < s.sma_50),
                    ('macd_bullish_cross', 'Bullische MACD-Kreuzung', 'Bullish MACD crossover', 'positive', s.previous_macd <= s.previous_macd_signal AND s.macd > s.macd_signal),
                    ('macd_bearish_cross', 'Bärische MACD-Kreuzung', 'Bearish MACD crossover', 'negative', s.previous_macd >= s.previous_macd_signal AND s.macd < s.macd_signal),
                    ('rsi_oversold', 'RSI überverkauft', 'RSI oversold', 'positive', s.previous_rsi >= 30 AND s.rsi_14 < 30),
                    ('rsi_overbought', 'RSI überkauft', 'RSI overbought', 'negative', s.previous_rsi <= 70 AND s.rsi_14 > 70),
                    ('resistance_breakout', 'Widerstand überschritten', 'Resistance broken', 'positive', s.previous_close <= s.previous_bollinger_upper AND s.close > s.bollinger_upper),
                    ('support_breakdown', 'Unterstützung unterschritten', 'Support broken', 'negative', s.previous_close >= s.previous_bollinger_lower AND s.close < s.bollinger_lower),
                    ('pattern_bullish_engulfing', 'Chartmuster: Bullish Engulfing', 'Chart pattern: Bullish Engulfing', 'positive', s.close > s.open AND s.previous_close < s.previous_open AND s.open <= s.previous_close AND s.close >= s.previous_open),
                    ('pattern_bearish_engulfing', 'Chartmuster: Bearish Engulfing', 'Chart pattern: Bearish Engulfing', 'negative', s.close < s.open AND s.previous_close > s.previous_open AND s.open >= s.previous_close AND s.close <= s.previous_open),
                    ('pattern_bullish_pin_bar', 'Chartmuster: Bullish Pin Bar', 'Chart pattern: Bullish Pin Bar', 'positive', (s.high - s.low) > 0 AND (LEAST(s.open, s.close) - s.low) >= 2 * GREATEST(ABS(s.close - s.open), (s.high - s.low) * .05) AND (s.high - GREATEST(s.open, s.close)) <= ABS(s.close - s.open)),
                    ('pattern_bearish_pin_bar', 'Chartmuster: Bearish Pin Bar', 'Chart pattern: Bearish Pin Bar', 'negative', (s.high - s.low) > 0 AND (s.high - GREATEST(s.open, s.close)) >= 2 * GREATEST(ABS(s.close - s.open), (s.high - s.low) * .05) AND (LEAST(s.open, s.close) - s.low) <= ABS(s.close - s.open)),
                    ('pattern_upside_breakout', 'Chartmuster: 20-Tage-Ausbruch nach oben', 'Chart pattern: 20-day upside breakout', 'positive', s.close > s.prior_20_high),
                    ('pattern_downside_breakout', 'Chartmuster: 20-Tage-Ausbruch nach unten', 'Chart pattern: 20-day downside breakout', 'negative', s.close < s.prior_20_low)
                ) AS event(event_key, label_de, label_en, tone, triggered)
                WHERE event.triggered
            SQL);
            DB::statement('CREATE INDEX chartview_detected_events_lookup ON chartview_detected_events (event_key, instrument_id, bar_time)');

            DB::statement(<<<'SQL'
                INSERT INTO chartview_signal_statistics
                    (event_key, label_de, label_en, tone, lookback_years, horizon_days, sample_size, rising_count, rise_probability, average_return, calculated_at, created_at, updated_at)
                SELECT event_key, MAX(label_de), MAX(label_en), MAX(tone), 3, 20,
                       COUNT(*) FILTER (WHERE forward_close IS NOT NULL AND bar_time >= CURRENT_DATE - INTERVAL '3 years'),
                       COUNT(*) FILTER (WHERE forward_close > close AND bar_time >= CURRENT_DATE - INTERVAL '3 years'),
                       AVG((forward_close > close)::int * 100.0) FILTER (WHERE forward_close IS NOT NULL AND bar_time >= CURRENT_DATE - INTERVAL '3 years'),
                       AVG(((forward_close / NULLIF(close, 0)) - 1) * 100) FILTER (WHERE forward_close IS NOT NULL AND bar_time >= CURRENT_DATE - INTERVAL '3 years'),
                       NOW(), NOW(), NOW()
                FROM chartview_detected_events GROUP BY event_key
                ON CONFLICT (event_key) DO UPDATE SET label_de=EXCLUDED.label_de, label_en=EXCLUDED.label_en,
                    tone=EXCLUDED.tone, sample_size=EXCLUDED.sample_size, rising_count=EXCLUDED.rising_count,
                    rise_probability=EXCLUDED.rise_probability, average_return=EXCLUDED.average_return,
                    calculated_at=EXCLUDED.calculated_at, updated_at=NOW()
            SQL);

            DB::table('chartview_instrument_signal_statistics')->delete();
            DB::statement(<<<'SQL'
                INSERT INTO chartview_instrument_signal_statistics
                    (instrument_id, event_key, lookback_years, horizon_days, sample_size, rising_count, rise_probability, average_return, calculated_at, created_at, updated_at)
                SELECT instrument_id, event_key, 3, 20, COUNT(*), COUNT(*) FILTER (WHERE forward_close > close),
                       AVG((forward_close > close)::int * 100.0),
                       AVG(((forward_close / NULLIF(close, 0)) - 1) * 100), NOW(), NOW(), NOW()
                FROM chartview_detected_events
                WHERE forward_close IS NOT NULL AND bar_time >= CURRENT_DATE - INTERVAL '3 years'
                GROUP BY instrument_id, event_key
            SQL);

            DB::statement(<<<'SQL'
                WITH trading_days AS (
                    SELECT DISTINCT bar_time::date AS trading_day FROM technical_indicators
                    WHERE interval='1d' ORDER BY trading_day DESC LIMIT 3
                ), recent AS (
                    SELECT DISTINCT instrument_id, bar_time, event_key, tone
                    FROM chartview_detected_events WHERE bar_time::date IN (SELECT trading_day FROM trading_days)
                ), scored AS (
                    SELECT r.*, g.rise_probability AS global_probability,
                           i.rise_probability AS instrument_probability, COALESCE(i.sample_size, 0) AS instrument_samples,
                           CASE WHEN COALESCE(i.sample_size,0) < 10 THEN g.rise_probability
                                ELSE i.rise_probability * (i.sample_size::numeric / (i.sample_size + 20))
                                   + g.rise_probability * (20::numeric / (i.sample_size + 20)) END AS blended_probability,
                           CASE WHEN COALESCE(i.sample_size,0) < 10 THEN 'global'
                                WHEN i.sample_size < 30 THEN 'blended' ELSE 'instrument' END AS probability_scope
                    FROM recent r JOIN chartview_signal_statistics g ON g.event_key=r.event_key
                    LEFT JOIN chartview_instrument_signal_statistics i ON i.instrument_id=r.instrument_id AND i.event_key=r.event_key
                )
                INSERT INTO chartview_signal_events
                    (instrument_id, bar_time, event_key, tone, rise_probability, global_probability, instrument_probability, probability_scope, sample_size, created_at, updated_at)
                SELECT instrument_id, bar_time, event_key, tone, blended_probability, global_probability,
                       instrument_probability, probability_scope, instrument_samples, NOW(), NOW() FROM scored
                ON CONFLICT (instrument_id, bar_time, event_key) DO UPDATE SET tone=EXCLUDED.tone,
                    rise_probability=EXCLUDED.rise_probability, global_probability=EXCLUDED.global_probability,
                    instrument_probability=EXCLUDED.instrument_probability, probability_scope=EXCLUDED.probability_scope,
                    sample_size=EXCLUDED.sample_size, updated_at=NOW()
            SQL);
            DB::statement(<<<'SQL'
                DELETE FROM chartview_signal_events WHERE bar_time::date < (
                    SELECT MIN(trading_day) FROM (
                        SELECT DISTINCT bar_time::date AS trading_day FROM technical_indicators
                        WHERE interval='1d' ORDER BY trading_day DESC LIMIT 3
                    ) latest_trading_days
                )
            SQL);
        }, 3);

        Cache::forget('dashboard.personal.signal-cockpit-v1');
        Cache::forget('dashboard.personal.signal-cockpit-v2');
        $this->info('ChartView global and stock-specific probabilities refreshed.');
        return self::SUCCESS;
    }
}
