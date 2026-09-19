<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the N historical BUY signals whose predicted return most
 * closely matches a given prediction's magnitude into a win-rate/average-
 * outcome statistic - a broader, less noise-prone companion to
 * TodayHighlightsBuilder::findAnalog()'s single nearest case. Meant to sit
 * next to composite_score as an independent "does history back this up"
 * read, not to be folded into the score itself.
 */
class HistoricalAnalogStatsService
{
    private const DEFAULT_SAMPLE_SIZE = 20;

    /** Signals must be at least this many days old so their outcome (net_return) is actually resolved. */
    private const MIN_SIGNAL_AGE_DAYS = 25;

    /** @return array{count: int, win_rate: float, avg_return: float, median_return: float}|null */
    public function aggregate(float $comparisonReturn, int $horizonDays, ?string $asOfDate = null, int $sampleSize = self::DEFAULT_SAMPLE_SIZE): ?array
    {
        $cutoff = Carbon::parse($asOfDate ?? now())->subDays(self::MIN_SIGNAL_AGE_DAYS)->toDateString();

        $returns = DB::table('walk_forward_backtest_trades')
            ->where('signal', 'BUY')
            ->where('horizon_days', $horizonDays)
            ->where('signal_date', '<=', $cutoff)
            ->orderByRaw('ABS(predicted_return - ?) ASC', [$comparisonReturn])
            ->limit($sampleSize)
            ->pluck('net_return')
            ->map(fn ($v) => (float) $v)
            ->values();

        if ($returns->isEmpty()) {
            return null;
        }

        $sorted = $returns->sort()->values();
        $n = $sorted->count();
        $median = $n % 2 === 0
            ? ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2
            : $sorted[intdiv($n, 2)];

        return [
            'count' => $n,
            'win_rate' => round($returns->filter(fn (float $v): bool => $v > 0)->count() / $n * 100, 1),
            'avg_return' => round($returns->avg() * 100, 1),
            'median_return' => round($median * 100, 1),
        ];
    }
}
