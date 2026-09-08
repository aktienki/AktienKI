<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HistoricalIndicatorMatrixService
{
    /** @var array<int, Collection<int, object>> */
    private array $series = [];

    public function filterEntries(Collection $trades, array $filters): Collection
    {
        if (($filters['indicator_matrix_usage'] ?? 'off') !== 'entry') return $trades;
        $this->load($trades->pluck('instrument_id')->map(fn ($id) => (int) $id)->unique());

        return $trades->filter(function (object $trade) use ($filters): bool {
            $point = $this->pointAt((int) $trade->instrument_id, (string) $trade->entry_date);
            return $point !== null && $this->matches($point, $filters);
        })->values();
    }

    public function applyExits(int $runId, array $filters): array
    {
        if (($filters['indicator_matrix_usage'] ?? 'off') !== 'exit') return [];
        $trades = DB::table('backtest_trades')->where('backtest_run_id', $runId)->get();
        $this->load($trades->pluck('instrument_id')->map(fn ($id) => (int) $id)->unique());
        $changed = 0;

        foreach ($trades as $trade) {
            $points = $this->series[(int) $trade->instrument_id] ?? collect();
            $match = $points->first(fn (object $point): bool =>
                $point->date > (string) $trade->entry_date
                && $point->date <= (string) $trade->exit_date
                && $this->matches($point, $filters));
            if ($match === null || (float) $trade->entry_price <= 0) continue;

            $bars = DB::table('price_bars')->where('instrument_id', $trade->instrument_id)
                ->where('interval', '1d')->whereDate('bar_time', '>=', $trade->entry_date)
                ->whereDate('bar_time', '<=', $match->date)->orderBy('bar_time')->get(['bar_time', 'low', 'close']);
            $exitBar = $bars->last();
            if ($exitBar === null || (float) $exitBar->close <= 0) continue;
            $entry = (float) $trade->entry_price;
            $exit = (float) $exitBar->close;
            $drawdown = $bars->min(fn (object $bar): float => ((float) $bar->low - $entry) / $entry);
            $metadata = is_string($trade->metadata) ? (json_decode($trade->metadata, true) ?: []) : (array) $trade->metadata;

            DB::table('backtest_trades')->where('id', $trade->id)->update([
                'exit_date' => substr((string) $exitBar->bar_time, 0, 10),
                'exit_price' => $exit,
                'gross_return' => ($exit - $entry) / $entry,
                'net_return' => (($exit - $entry) / $entry) - (float) $trade->transaction_cost,
                'max_drawdown' => $drawdown,
                'metadata' => json_encode([...$metadata, 'indicator_matrix_exit' => [
                    'matrix' => 'macd_stochastic', 'preset' => $filters['indicator_matrix_preset'] ?? 'manual',
                    'date' => $match->date, 'macd_percent' => $match->macd_percent,
                    'stochastic_k' => $match->stochastic_k,
                ]], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $changed++;
        }

        return ['matrix' => 'macd_stochastic', 'usage' => 'exit', 'early_exits' => $changed];
    }

    private function load(Collection $instrumentIds): void
    {
        $missing = $instrumentIds->filter(fn (int $id): bool => ! array_key_exists($id, $this->series))->values();
        if ($missing->isEmpty()) return;
        $rows = DB::table('technical_indicators as ti')
            ->join('price_bars as pb', function ($join): void {
                $join->on('pb.instrument_id', '=', 'ti.instrument_id')->on('pb.interval', '=', 'ti.interval')
                    ->whereRaw('DATE(pb.bar_time) = DATE(ti.bar_time)');
            })
            ->where('ti.interval', '1d')->whereIn('ti.instrument_id', $missing)
            ->where('ti.bar_time', '>=', now()->subYears(3)->subDays(40))
            ->whereNotNull('ti.macd_histogram')->whereNotNull('ti.stochastic_k')->where('pb.close', '>', 0)
            ->orderBy('ti.instrument_id')->orderBy('ti.bar_time')
            ->get(['ti.instrument_id', 'ti.bar_time', 'ti.macd_histogram', 'ti.stochastic_k', 'pb.close']);
        foreach ($missing as $id) $this->series[$id] = collect();
        foreach ($rows->groupBy('instrument_id') as $id => $group) {
            $previous = null;
            $this->series[(int) $id] = $group->map(function (object $row) use (&$previous): object {
                $value = (float) $row->macd_histogram / (float) $row->close * 100;
                $point = (object) [
                    'date' => substr((string) $row->bar_time, 0, 10),
                    'macd_percent' => $value,
                    'previous_macd_percent' => $previous,
                    'stochastic_k' => (float) $row->stochastic_k,
                ];
                $previous = $value;
                return $point;
            })->values();
        }
    }

    private function pointAt(int $instrumentId, string $date): ?object
    {
        return ($this->series[$instrumentId] ?? collect())->last(fn (object $point): bool => $point->date <= $date);
    }

    private function matches(object $point, array $filters): bool
    {
        $preset = (string) ($filters['indicator_matrix_preset'] ?? 'manual');
        $macd = (float) $point->macd_percent;
        $previous = $point->previous_macd_percent;
        $stoch = (float) $point->stochastic_k;
        if ($previous === null) return false;

        return match ($preset) {
            'oversold_recovery' => $stoch <= 25 && $macd > (float) $previous,
            'early_recovery' => $stoch > 20 && $stoch <= 50 && $macd > (float) $previous,
            'bullish_impulse' => $stoch > 50 && $stoch < 80 && $macd > 0 && $macd >= (float) $previous,
            'overheated_fading' => $stoch >= 80 && $macd < (float) $previous,
            'bearish_impulse' => $stoch <= 50 && $macd < 0 && $macd <= (float) $previous,
            default => $this->manualMatch($macd, (float) $previous, $stoch, $filters),
        };
    }

    private function manualMatch(float $macd, float $previous, float $stoch, array $filters): bool
    {
        $direction = (string) ($filters['indicator_matrix_macd_direction'] ?? 'any');
        return $macd >= (float) ($filters['indicator_matrix_macd_min'] ?? -100)
            && $macd <= (float) ($filters['indicator_matrix_macd_max'] ?? 100)
            && $stoch >= (float) ($filters['indicator_matrix_stoch_min'] ?? 0)
            && $stoch <= (float) ($filters['indicator_matrix_stoch_max'] ?? 100)
            && ($direction === 'any' || ($direction === 'rising' && $macd > $previous) || ($direction === 'falling' && $macd < $previous));
    }
}
