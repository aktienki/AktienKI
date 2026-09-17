<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Builds the four "Heute im Fokus" highlights (top BUY signal, biggest
 * held-position swing, surprise signal against yesterday's trend, and a
 * SELL→BUY flip) from today_highlights_mv and the live portfolio positions.
 * Shared by the dashboard section and the daily Grid analysis command so
 * both always see the same data.
 */
final class TodayHighlightsBuilder
{
    private const COUNTRY_FLAGS = [
        'DE' => '🇩🇪', 'US' => '🇺🇸', 'AT' => '🇦🇹', 'CH' => '🇨🇭', 'GB' => '🇬🇧', 'FR' => '🇫🇷',
        'NL' => '🇳🇱', 'DK' => '🇩🇰', 'SE' => '🇸🇪', 'NO' => '🇳🇴', 'FI' => '🇫🇮', 'IT' => '🇮🇹',
        'ES' => '🇪🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'HK' => '🇭🇰', 'CA' => '🇨🇦', 'AU' => '🇦🇺',
    ];

    public function build(?string $date = null): array
    {
        $date ??= now()->toDateString();

        $topSignal = null;
        $swingStock = null;
        $surpriseSignal = null;
        $trendSwitch = null;
        $analogs = [];

        $topBuy = DB::table('today_highlights_mv')
            ->where('serving_signal', 'BUY')
            ->whereDate('prediction_date', $date)
            ->orderByDesc('expected_return')
            ->select('instrument_id', 'expected_return', 'symbol', 'name')
            ->first();

        if ($topBuy) {
            $topSignal = [
                'symbol' => $topBuy->symbol,
                'name' => $topBuy->name,
                'return' => (float) ($topBuy->expected_return ?? 0) * 100,
                'url' => route('stocks.show', ['symbol' => $topBuy->symbol, 'return_to' => '/dashboard/concept']),
                'details' => $this->enrich((int) $topBuy->instrument_id),
            ];
            array_push($analogs, ...$this->findAnalogs(
                (int) $topBuy->instrument_id,
                $topBuy->symbol,
                (float) $topBuy->expected_return,
                $date,
            ));
        }

        $holdingSwings = DB::table('portfolio_positions as pp')
            ->join('instruments as i', 'i.id', '=', 'pp.instrument_id')
            ->select([
                'pp.instrument_id',
                'i.symbol',
                'i.name',
                'pp.current_price',
                'pp.average_buy_price',
                DB::raw('(pp.current_price - pp.average_buy_price) / NULLIF(pp.average_buy_price, 0) * 100 as perf_pct'),
            ])
            ->where('pp.quantity', '>', 0)
            ->orderByDesc(DB::raw('ABS((pp.current_price - pp.average_buy_price) / NULLIF(pp.average_buy_price, 0) * 100)'))
            ->first();

        if ($holdingSwings) {
            $swingStock = [
                'symbol' => $holdingSwings->symbol,
                'name' => $holdingSwings->name,
                'perf_pct' => (float) $holdingSwings->perf_pct,
                'url' => route('stocks.show', ['symbol' => $holdingSwings->symbol, 'return_to' => '/dashboard/concept']),
                'details' => $this->enrich((int) $holdingSwings->instrument_id),
            ];
        }

        $surprise = DB::table('today_highlights_mv')
            ->where('serving_signal', 'BUY')
            ->whereIn('predictions_signal', ['SELL', 'HOLD'])
            ->whereDate('prediction_date', $date)
            ->orderByDesc('expected_return')
            ->select('instrument_id', 'expected_return', 'symbol', 'name')
            ->first();

        if ($surprise) {
            $surpriseSignal = [
                'symbol' => $surprise->symbol,
                'name' => $surprise->name,
                'return' => (float) ($surprise->expected_return ?? 0) * 100,
                'url' => route('stocks.show', ['symbol' => $surprise->symbol, 'return_to' => '/dashboard/concept']),
                'details' => $this->enrich((int) $surprise->instrument_id),
            ];
            array_push($analogs, ...$this->findAnalogs(
                (int) $surprise->instrument_id,
                $surprise->symbol,
                (float) $surprise->expected_return,
                $date,
            ));
        }

        $trendSwitchRow = DB::table('today_highlights_mv')
            ->where('predictions_signal', 'SELL')
            ->where('serving_signal', 'BUY')
            ->whereDate('prediction_date', $date)
            ->select('instrument_id', 'symbol', 'name')
            ->first();

        if ($trendSwitchRow) {
            $trendSwitch = [
                'symbol' => $trendSwitchRow->symbol,
                'name' => $trendSwitchRow->name,
                'url' => route('stocks.show', ['symbol' => $trendSwitchRow->symbol, 'return_to' => '/dashboard/concept']),
                'details' => $this->enrich((int) $trendSwitchRow->instrument_id),
            ];
        }

        $highlights = [
            [
                'label' => __('Top-Signal'),
                'subtitle' => __('Beste neue BUY-Empfehlung'),
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'emerald',
                'data' => $topSignal ? sprintf('%s +%.1f%%', $topSignal['symbol'], $topSignal['return']) : null,
                'url' => $topSignal['url'] ?? null,
                'details' => $topSignal['details'] ?? null,
                'metric_label' => __('Erwartete Rendite'),
                'metric_value' => $topSignal ? sprintf('+%.1f%%', $topSignal['return']) : null,
            ],
            [
                'label' => __('Grösster Swing'),
                'subtitle' => __('Positionäre Performance heute'),
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'orange',
                'data' => $swingStock ? sprintf('%s %+.1f%%', $swingStock['symbol'], $swingStock['perf_pct']) : null,
                'url' => $swingStock['url'] ?? null,
                'details' => $swingStock['details'] ?? null,
                'metric_label' => __('Performance seit Kauf'),
                'metric_value' => $swingStock ? sprintf('%+.1f%%', $swingStock['perf_pct']) : null,
            ],
            [
                'label' => __('Überraschung'),
                'subtitle' => __('Signal gegen den Trend'),
                'icon' => 'heroicon-o-bolt',
                'color' => 'yellow',
                'data' => $surpriseSignal ? sprintf('%s (war HOLD)', $surpriseSignal['symbol']) : null,
                'url' => $surpriseSignal['url'] ?? null,
                'details' => $surpriseSignal['details'] ?? null,
                'metric_label' => __('Erwartete Rendite'),
                'metric_value' => $surpriseSignal ? sprintf('+%.1f%%', $surpriseSignal['return']) : null,
            ],
            [
                'label' => __('Trendwechsel'),
                'subtitle' => __('Von SELL zu BUY geflipped'),
                'icon' => 'heroicon-o-arrow-path',
                'color' => 'cyan',
                'data' => $trendSwitch ? $trendSwitch['symbol'] : null,
                'url' => $trendSwitch['url'] ?? null,
                'details' => $trendSwitch['details'] ?? null,
                'metric_label' => __('Neues Signal'),
                'metric_value' => $trendSwitch ? __('BUY') : null,
            ],
        ];

        $analogs = collect($analogs)
            ->unique(fn (array $a) => $a['type'].'|'.$a['analog_symbol'])
            ->values()
            ->all();

        return ['highlights' => $highlights, 'analogs' => $analogs];
    }

    /**
     * Finds up to two historical analogs for a BUY signal of a given
     * predicted-return magnitude: the same instrument's most similar past
     * BUY signal, and the most similar past BUY signal on any other
     * instrument - both with a resolved (>=25 days old) real outcome.
     *
     * @return list<array<string, mixed>>
     */
    private function findAnalogs(int $instrumentId, string $symbol, float $predictedReturn, string $date): array
    {
        $cutoff = \Illuminate\Support\Carbon::parse($date)->subDays(25)->toDateString();
        $analogs = [];

        $sameStock = DB::table('walk_forward_backtest_trades')
            ->where('instrument_id', $instrumentId)
            ->where('signal', 'BUY')
            ->where('horizon_days', 20)
            ->where('signal_date', '<=', $cutoff)
            ->orderByRaw('ABS(predicted_return - ?) ASC', [$predictedReturn])
            ->select('signal_date', 'net_return')
            ->first();

        if ($sameStock) {
            $analogs[] = [
                'type' => 'same_stock',
                'symbol' => $symbol,
                'analog_symbol' => $symbol,
                'signal_date' => $sameStock->signal_date,
                'outcome_pct' => round((float) $sameStock->net_return * 100, 1),
                'url' => route('stocks.show', ['symbol' => $symbol, 'return_to' => '/dashboard/concept']),
            ];
        }

        $crossStock = DB::table('walk_forward_backtest_trades as t')
            ->join('instruments as i', 'i.id', '=', 't.instrument_id')
            ->where('t.instrument_id', '!=', $instrumentId)
            ->where('t.signal', 'BUY')
            ->where('t.horizon_days', 20)
            ->where('t.signal_date', '<=', $cutoff)
            ->orderByRaw('ABS(t.predicted_return - ?) ASC', [$predictedReturn])
            ->select('i.symbol', 't.signal_date', 't.net_return')
            ->first();

        if ($crossStock) {
            $analogs[] = [
                'type' => 'cross_stock',
                'symbol' => $symbol,
                'analog_symbol' => $crossStock->symbol,
                'signal_date' => $crossStock->signal_date,
                'outcome_pct' => round((float) $crossStock->net_return * 100, 1),
                'url' => route('stocks.show', ['symbol' => $crossStock->symbol, 'return_to' => '/dashboard/concept']),
            ];
        }

        return $analogs;
    }

    /** @return array<string, mixed> */
    private function enrich(int $instrumentId): array
    {
        $instrument = DB::table('instruments')
            ->where('id', $instrumentId)
            ->select('country', 'sector', 'currency')
            ->first();

        $quote = DB::table('current_stock_quotes')
            ->where('instrument_id', $instrumentId)
            ->where('status', 'current')
            ->orderByDesc('quote_time')
            ->value('price');

        // calibrated_score is a formatted return-percentage string (e.g.
        // "+13.67%"), not a 0-10 quality score - it's redundant with the
        // card's own metric_value, so it isn't surfaced here. Pin to the
        // standard/20-day variant so this doesn't land on an arbitrary one
        // of the several horizon/variant rows sharing the same as_of.
        $serving = DB::connection('serving')->table('serving_predictions')
            ->where('instrument_id', $instrumentId)
            ->where('variant', 'standard')
            ->where('horizon', 20)
            ->orderByDesc('as_of')
            ->select('risk_score', 'confidence')
            ->first();

        $country = strtoupper((string) ($instrument->country ?? ''));

        return [
            'country' => $country,
            'country_flag' => self::COUNTRY_FLAGS[$country] ?? '🌐',
            'sector' => $instrument->sector ?? null,
            'currency' => $instrument->currency ?? null,
            'current_price' => is_numeric($quote) ? (float) $quote : null,
            'risk' => is_numeric($serving?->risk_score ?? null) ? round((float) $serving->risk_score, 1) : null,
            'confidence' => is_numeric($serving?->confidence ?? null)
                ? round((float) $serving->confidence <= 1 ? (float) $serving->confidence * 100 : (float) $serving->confidence)
                : null,
        ];
    }
}
