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
    public function build(?string $date = null): array
    {
        $date ??= now()->toDateString();

        $topSignal = null;
        $swingStock = null;
        $surpriseSignal = null;
        $trendSwitch = null;

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
            ];
        }

        $holdingSwings = DB::table('portfolio_positions as pp')
            ->join('instruments as i', 'i.id', '=', 'pp.instrument_id')
            ->select([
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
            ];
        }

        $trendSwitchRow = DB::table('today_highlights_mv')
            ->where('predictions_signal', 'SELL')
            ->where('serving_signal', 'BUY')
            ->whereDate('prediction_date', $date)
            ->select('symbol', 'name')
            ->first();

        if ($trendSwitchRow) {
            $trendSwitch = [
                'symbol' => $trendSwitchRow->symbol,
                'name' => $trendSwitchRow->name,
                'url' => route('stocks.show', ['symbol' => $trendSwitchRow->symbol, 'return_to' => '/dashboard/concept']),
            ];
        }

        return [
            [
                'label' => __('Top-Signal'),
                'subtitle' => __('Beste neue BUY-Empfehlung'),
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'emerald',
                'data' => $topSignal ? sprintf('%s +%.1f%%', $topSignal['symbol'], $topSignal['return']) : null,
                'url' => $topSignal['url'] ?? null,
            ],
            [
                'label' => __('Grösster Swing'),
                'subtitle' => __('Positionäre Performance heute'),
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'orange',
                'data' => $swingStock ? sprintf('%s %+.1f%%', $swingStock['symbol'], $swingStock['perf_pct']) : null,
                'url' => $swingStock['url'] ?? null,
            ],
            [
                'label' => __('Überraschung'),
                'subtitle' => __('Signal gegen den Trend'),
                'icon' => 'heroicon-o-bolt',
                'color' => 'yellow',
                'data' => $surpriseSignal ? sprintf('%s (war HOLD)', $surpriseSignal['symbol']) : null,
                'url' => $surpriseSignal['url'] ?? null,
            ],
            [
                'label' => __('Trendwechsel'),
                'subtitle' => __('Von SELL zu BUY geflipped'),
                'icon' => 'heroicon-o-arrow-path',
                'color' => 'cyan',
                'data' => $trendSwitch ? $trendSwitch['symbol'] : null,
                'url' => $trendSwitch['url'] ?? null,
            ],
        ];
    }
}
