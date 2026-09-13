<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ServingWalkForwardStatisticsService
{
    /**
     * Build a strict three-year chronological split for every active release:
     * 24 months of selection statistics followed by a twelve-month walk-forward
     * window. A trade belongs to a window only after its exit is known.
     *
     * @return Collection<string, array{statistics: object, walk_forward: object, walk_forward_passed: bool, statistics_from: string, statistics_to: string, walk_forward_from: string, walk_forward_to: string}>
     */
    public function forActiveStocks(Collection $stocks, string $connectionName = 'serving'): Collection
    {
        if ($stocks->isEmpty()) {
            return collect();
        }

        $connection = DB::connection($connectionName);
        $cutoffs = $stocks->keyBy('instrument_id');

        // Resolve the newest complete run once per instrument, horizon and
        // variant. Doing this before loading trades avoids repeating the run
        // JSON metadata for every trade (tens of thousands of rows).
        $runs = $connection
            ->table('serving_strategy_trades as trade')
            ->join('serving_strategy_runs as run', 'run.id', '=', 'trade.strategy_run_id')
            ->join('serving_active_models as active', function ($join): void {
                $join->on('active.instrument_id', '=', 'trade.instrument_id')
                    ->whereRaw("run.source_metadata->>'release_id' = active.release_id::text");
            })
            ->where('run.status', 'complete')
            ->whereIn('trade.instrument_id', $stocks->pluck('instrument_id')->all())
            ->where('trade.entry_signal', 'BUY')
            ->whereNotNull('trade.exit_date')
            ->whereNotNull('trade.net_return')
            ->distinct()
            ->get([
                'trade.instrument_id', 'trade.horizon', 'trade.strategy_run_id', 'trade.strategy',
                'run.calculation_date', 'run.finished_at', 'run.source_metadata',
            ])
            ->map(function (object $run): array {
                return [
                    'run_id' => (string) $run->strategy_run_id,
                    'instrument_id' => (int) $run->instrument_id,
                    'horizon' => (int) $run->horizon,
                    'variant' => $this->variant($run),
                    'calculation_date' => (string) $run->calculation_date,
                    'finished_at' => (string) $run->finished_at,
                ];
            })
            ->groupBy(fn (array $run): string => implode('|', [
                $run['instrument_id'], $run['horizon'], $run['variant'],
            ]))
            ->map(fn (Collection $candidates): array => $candidates
                ->sortByDesc(fn (array $run): string => $run['calculation_date'].'|'.$run['finished_at'].'|'.$run['run_id'])
                ->first());

        if ($runs->isEmpty()) {
            return collect();
        }

        $buckets = $runs->map(function (array $run) use ($cutoffs): array {
            $stock = $cutoffs->get($run['instrument_id']);
            $cutoff = Carbon::parse((string) ($stock->dataset_cutoff ?? $run['calculation_date']))->startOfDay();

            return $run + [
                'cutoff' => $cutoff,
                'walk_forward_start' => $cutoff->copy()->subYear(),
                'statistics_start' => $cutoff->copy()->subYears(3),
                'statistics_returns' => [],
                'statistics_entry_scores' => [],
                'walk_forward_returns' => [],
                'walk_forward_entry_scores' => [],
            ];
        });
        $bucketKeysByTrade = $buckets->mapWithKeys(fn (array $bucket, string $key): array => [
            implode('|', [$bucket['run_id'], $bucket['instrument_id'], $bucket['horizon']]) => $key,
        ]);

        // Stream only the compact trade fields. Retaining numeric returns per
        // bucket uses a fraction of the memory of materialising every DB row.
        $connection->table('serving_strategy_trades as trade')
            ->whereIn('trade.strategy_run_id', $buckets->pluck('run_id')->unique()->all())
            ->whereIn('trade.instrument_id', $stocks->pluck('instrument_id')->all())
            ->where('trade.entry_signal', 'BUY')
            ->whereNotNull('trade.exit_date')
            ->whereNotNull('trade.net_return')
            ->orderBy('trade.exit_date')
            ->select([
                'trade.strategy_run_id', 'trade.instrument_id', 'trade.horizon',
                'trade.entry_date', 'trade.exit_date', 'trade.net_return', 'trade.entry_tcn_score',
            ])
            ->cursor()
            ->each(function (object $trade) use (&$buckets, $bucketKeysByTrade): void {
                $tradeKey = implode('|', [
                    (string) $trade->strategy_run_id,
                    (int) $trade->instrument_id,
                    (int) $trade->horizon,
                ]);
                $bucketKey = $bucketKeysByTrade->get($tradeKey);
                if ($bucketKey === null) {
                    return;
                }

                $bucket = $buckets->get($bucketKey);
                $entry = Carbon::parse((string) $trade->entry_date)->startOfDay();
                $exit = Carbon::parse((string) $trade->exit_date)->startOfDay();
                $return = (float) $trade->net_return;
                if ($exit->gte($bucket['statistics_start']) && $exit->lt($bucket['walk_forward_start'])) {
                    $bucket['statistics_returns'][] = $return;
                    if (is_numeric($trade->entry_tcn_score ?? null)) {
                        $bucket['statistics_entry_scores'][] = (float) $trade->entry_tcn_score;
                    }
                }
                if ($entry->gte($bucket['walk_forward_start']) && $exit->lte($bucket['cutoff'])) {
                    $bucket['walk_forward_returns'][] = $return;
                    if (is_numeric($trade->entry_tcn_score ?? null)) {
                        $bucket['walk_forward_entry_scores'][] = (float) $trade->entry_tcn_score;
                    }
                }
                $buckets->put($bucketKey, $bucket);
            });

        return $buckets->map(function (array $bucket): array {
            $statistics = $this->metrics(
                collect($bucket['statistics_returns']),
                collect($bucket['statistics_entry_scores']),
            );
            $walkForward = $this->metrics(
                collect($bucket['walk_forward_returns']),
                collect($bucket['walk_forward_entry_scores']),
            );

            return [
                'statistics' => $statistics,
                'walk_forward' => $walkForward,
                'walk_forward_passed' => $walkForward->trades > 0
                    && is_numeric($walkForward->average_return)
                    && $walkForward->average_return > 0,
                'statistics_from' => $bucket['statistics_start']->toDateString(),
                'statistics_to' => $bucket['walk_forward_start']->copy()->subDay()->toDateString(),
                'walk_forward_from' => $bucket['walk_forward_start']->toDateString(),
                'walk_forward_to' => $bucket['cutoff']->toDateString(),
            ];
        });
    }

    /** @return object{trades: int, hit_rate: ?float, profit_factor: ?float, average_return: ?float, median_return: ?float, cumulative_return: ?float, max_drawdown: ?float, volatility: ?float, average_entry_score: ?float} */
    public function metrics(Collection $trades, ?Collection $entryScores = null): object
    {
        $orderedTrades = $trades->every(fn (mixed $trade): bool => is_numeric($trade))
            ? $trades
            : $trades->sortBy('exit_date');
        $returns = $orderedTrades
            ->map(fn (mixed $trade): mixed => is_numeric($trade) ? $trade : data_get($trade, 'net_return'))
            ->filter(fn (mixed $value): bool => is_numeric($value) && is_finite((float) $value))
            ->map(fn (mixed $value): float => (float) $value)
            ->values();
        if ($returns->isEmpty()) {
            return (object) [
                'trades' => 0, 'hit_rate' => null, 'profit_factor' => null,
                'average_return' => null, 'median_return' => null,
                'cumulative_return' => null, 'max_drawdown' => null, 'volatility' => null,
                'average_entry_score' => null,
            ];
        }

        $equity = 1.0;
        $peak = 1.0;
        $maximumDrawdown = 0.0;
        foreach ($returns as $return) {
            $equity *= 1.0 + $return;
            $peak = max($peak, $equity);
            $maximumDrawdown = min($maximumDrawdown, $equity / $peak - 1.0);
        }
        $wins = $returns->filter(fn (float $return): bool => $return > 0);
        $losses = abs($returns->filter(fn (float $return): bool => $return < 0)->sum());
        $sorted = $returns->sort()->values();
        $middle = intdiv($sorted->count(), 2);
        $median = $sorted->count() % 2 === 0
            ? ((float) $sorted[$middle - 1] + (float) $sorted[$middle]) / 2
            : (float) $sorted[$middle];
        $mean = (float) $returns->avg();
        $volatility = sqrt((float) $returns
            ->sum(fn (float $return): float => ($return - $mean) ** 2) / $returns->count());

        return (object) [
            'trades' => $returns->count(),
            'hit_rate' => $wins->count() / $returns->count() * 100,
            'profit_factor' => $losses > 0 ? $wins->sum() / $losses : ($wins->isNotEmpty() ? 999.0 : null),
            'average_return' => $returns->avg() * 100,
            'median_return' => $median * 100,
            'cumulative_return' => ($equity - 1.0) * 100,
            'max_drawdown' => $maximumDrawdown * 100,
            'volatility' => $volatility * 100,
            'average_entry_score' => $entryScores?->filter(fn (mixed $score): bool => is_numeric($score))
                ->avg(fn (mixed $score): float => (float) $score),
        ];
    }

    private function variant(object $trade): string
    {
        $metadata = is_array($trade->source_metadata)
            ? $trade->source_metadata
            : (json_decode((string) $trade->source_metadata, true) ?: []);
        $variant = strtolower((string) ($metadata['variant'] ?? ''));
        if (in_array($variant, ['standard', 'pure_tcn'], true)) {
            return $variant;
        }

        return str_contains(strtolower(str_replace('_', '-', (string) $trade->strategy)), 'pure-tcn')
            ? 'pure_tcn'
            : 'standard';
    }
}
