<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HistoricalIndicatorProbabilityService
{
    private const FIELDS = ['rsi_14', 'adx_14', 'stochastic_k', 'volatility_20', 'atr_14', 'bollinger_width', 'macd_histogram', 'momentum_10'];

    /**
     * Apply the same combined probability used by the stock screener without
     * leaking future outcomes into a historical strategy test.
     */
    public function filter(Collection $trades, float $minimum): Collection
    {
        if ($minimum <= 0 || $trades->isEmpty()) return $trades;

        $instrumentIds = $trades->pluck('instrument_id')->unique()->values();
        $rows = DB::table('technical_indicators as technical')
            ->join('feature_store as feature', function ($join): void {
                $join->on('feature.instrument_id', '=', 'technical.instrument_id')
                    ->on('feature.interval', '=', 'technical.interval')
                    ->on('feature.bar_time', '=', 'technical.bar_time');
            })
            ->whereIn('technical.instrument_id', $instrumentIds)
            ->where('technical.interval', '1d')
            ->whereNotNull('feature.target_return_20d')
            ->orderBy('technical.instrument_id')->orderBy('technical.bar_time')
            ->get(['technical.instrument_id', 'technical.bar_time', 'feature.close', 'feature.target_return_20d', ...array_map(fn (string $field): string => 'technical.'.$field, self::FIELDS)])
            ->groupBy('instrument_id');

        return $trades->filter(function (object $trade) use ($rows, $minimum): bool {
            $history = $rows->get($trade->instrument_id, collect())->values();
            $date = substr((string) $trade->entry_date, 0, 10);
            $currentIndex = $history->search(fn (object $row): bool => substr((string) $row->bar_time, 0, 10) === $date);
            if ($currentIndex === false || $currentIndex < 20) return false;

            $current = $history[$currentIndex];
            // A 20T target is only known roughly 20 trading rows later.
            $evidence = $history->slice(0, max(0, $currentIndex - 20));
            $probabilities = collect(self::FIELDS)->map(function (string $field) use ($current, $evidence): ?float {
                $value = $this->value($current, $field);
                if ($value === null) return null;
                $nearby = $evidence->filter(fn (object $row): bool => $this->value($row, $field) !== null)
                    ->sortBy(fn (object $row): float => abs($this->value($row, $field) - $value))
                    ->take(40);
                if ($nearby->isEmpty()) return null;
                return ($nearby->filter(fn (object $row): bool => (float) $row->target_return_20d > 0)->count() / $nearby->count()) * 100;
            })->filter(fn ($value): bool => $value !== null);

            $trade->indicator_probability = $probabilities->isEmpty() ? null : $probabilities->avg();
            return $trade->indicator_probability !== null && $trade->indicator_probability >= $minimum;
        })->values();
    }

    private function value(object $row, string $field): ?float
    {
        if ($field === 'momentum_10') {
            if (! is_numeric($row->momentum_10) || ! is_numeric($row->close)) return null;
            $denominator = (float) $row->close - (float) $row->momentum_10;
            return abs($denominator) > 0.000001 ? (float) $row->momentum_10 / $denominator : null;
        }
        return is_numeric($row->{$field} ?? null) ? (float) $row->{$field} : null;
    }
}
