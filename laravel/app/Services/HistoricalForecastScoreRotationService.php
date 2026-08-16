<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HistoricalForecastScoreRotationService
{
    public const STRATEGY = 'forecast_score_rotation_5d';

    public function apply(
        int $runId,
        int $maxPositions = 5,
        bool $enabled = false,
        bool $sectorRotation = false,
        bool $indexRotation = false,
        string $strategyPriority = 'rotation_first',
    ): array
    {
        DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $runId)
            ->where('strategy', self::STRATEGY)
            ->delete();

        if (! $enabled) {
            return ['strategy' => self::STRATEGY, 'enabled' => false, 'trades' => 0, 'replacements' => 0, 'rebalance_days' => 0];
        }

        $snapshots = DB::table('backtest_trades as trade')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->where('trade.backtest_run_id', $runId)
            ->whereNotNull('trade.predicted_return')
            ->whereBetween('trade.gross_return', [-1.0, 3.0])
            ->orderBy('trade.entry_date')
            ->orderByDesc('trade.predicted_return')
            ->orderByDesc('trade.ki_score')
            ->get([
                'trade.id', 'trade.instrument_id', 'trade.entry_date', 'trade.signal',
                'trade.predicted_return', 'trade.ki_score', 'trade.confidence', 'trade.entry_price',
                'instrument.sector',
            ]);

        if ($snapshots->isEmpty()) {
            return ['strategy' => self::STRATEGY, 'trades' => 0, 'replacements' => 0, 'rebalance_days' => 0];
        }

        $instrumentIds = $snapshots->pluck('instrument_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $start = (string) $snapshots->min('entry_date');
        $end = (string) DB::table('backtest_trades')->where('backtest_run_id', $runId)->max('exit_date');
        // Every walk-forward snapshot stores the actual price known on its prediction day.
        // It therefore provides a point-in-time-safe history even when the public chart
        // history was imported later. Real Twelve Data bars override these fallback points.
        $bars = $snapshots
            ->filter(fn (object $row): bool => (float) $row->entry_price > 0)
            ->groupBy(fn (object $row): int => (int) $row->instrument_id)
            ->map(fn (Collection $items): Collection => $items
                ->groupBy(fn (object $row): string => substr((string) $row->entry_date, 0, 10))
                ->map(function (Collection $dayRows): object {
                    $price = (float) $dayRows->first()->entry_price;

                    return (object) [
                        'open' => $price,
                        'high' => $price,
                        'low' => $price,
                        'close' => $price,
                        'source' => 'walk_forward_snapshot',
                    ];
                }));

        $databaseBars = DB::table('price_bars')
            ->whereIn('instrument_id', $instrumentIds)
            ->where('interval', '1d')
            ->whereDate('bar_time', '>=', $start)
            ->whereDate('bar_time', '<=', $end)
            ->orderBy('bar_time')
            ->get(['instrument_id', 'bar_time', 'open', 'high', 'low', 'close'])
            ->groupBy(fn (object $bar): int => (int) $bar->instrument_id)
            ->map(fn (Collection $items): Collection => $items->keyBy(fn (object $bar): string => substr((string) $bar->bar_time, 0, 10)));

        foreach ($databaseBars as $instrumentId => $instrumentBars) {
            $bars->put((int) $instrumentId, $bars->get((int) $instrumentId, collect())->merge($instrumentBars)->sortKeys());
        }

        $tradingDays = $bars
            ->flatMap(fn (Collection $instrumentBars): Collection => $instrumentBars->keys())
            ->unique()->sort()->values();
        $rebalanceDays = $tradingDays->filter(fn (string $day, int $index): bool => $index % 5 === 0)->values();
        if ($rebalanceDays->isEmpty() || $bars->isEmpty()) {
            return ['strategy' => self::STRATEGY, 'trades' => 0, 'replacements' => 0, 'rebalance_days' => 0];
        }

        $snapshotsByInstrument = $snapshots->groupBy(fn (object $row): int => (int) $row->instrument_id);
        $indexMemberships = $indexRotation
            ? DB::table('index_memberships')->whereNull('removed_at')->whereIn('instrument_id', $instrumentIds)
                ->get(['instrument_id', 'market_index_id'])->groupBy(fn (object $row): int => (int) $row->instrument_id)
            : collect();
        $latest = [];
        $positions = [];
        $usedSourceTrades = [];
        $completed = [];
        $replacements = 0;

        foreach ($rebalanceDays as $day) {
            foreach ($snapshotsByInstrument as $instrumentId => $instrumentSnapshots) {
                $available = $instrumentSnapshots->filter(fn (object $row): bool => (string) $row->entry_date <= $day)->last();
                if ($available !== null) $latest[(int) $instrumentId] = $available;
            }

            if ($strategyPriority === 'exit_first') {
                $this->closeDuePositions($positions, $completed, $day, $bars, $latest);
            }

            $sectorScores = $sectorRotation
                ? collect($latest)->filter(fn (object $row): bool => filled($row->sector ?? null))
                    ->groupBy('sector')->map(fn (Collection $rows): float => (float) $rows->avg('ki_score'))
                : collect();
            $bestSector = $sectorScores->sortDesc()->keys()->first();
            $indexScores = collect();
            if ($indexRotation) {
                foreach ($latest as $row) {
                    foreach ($indexMemberships->get((int) $row->instrument_id, collect()) as $membership) {
                        $indexScores->push(['index' => (int) $membership->market_index_id, 'score' => (float) $row->ki_score]);
                    }
                }
                $indexScores = $indexScores->groupBy('index')->map(fn (Collection $rows): float => (float) $rows->avg('score'));
            }
            $bestIndex = $indexScores->sortDesc()->keys()->first();

            $ranked = collect($latest)
                ->filter(fn (object $row): bool => strtoupper((string) ($row->signal ?? 'BUY')) === 'BUY'
                    && (float) $row->predicted_return > 0
                    && $this->priceOn($bars->get((int) $row->instrument_id), $day) !== null)
                ->sort(function (object $left, object $right) use ($sectorRotation, $indexRotation, $bestSector, $bestIndex, $indexMemberships): int {
                    $priority = function (object $row) use ($sectorRotation, $indexRotation, $bestSector, $bestIndex, $indexMemberships): int {
                        $sectorMatch = $sectorRotation && $bestSector !== null && ($row->sector ?? null) === $bestSector;
                        $indexMatch = $indexRotation && $bestIndex !== null
                            && $indexMemberships->get((int) $row->instrument_id, collect())->contains(fn (object $membership): bool => (int) $membership->market_index_id === (int) $bestIndex);
                        return (int) $sectorMatch + (int) $indexMatch;
                    };
                    return ($priority($right) <=> $priority($left))
                    ?: ((float) $right->predicted_return <=> (float) $left->predicted_return)
                    ?: ((float) $right->ki_score <=> (float) $left->ki_score)
                    ?: ((float) $right->confidence <=> (float) $left->confidence);
                })
                ->values();

            while (count($positions) < max(1, $maxPositions)) {
                $candidate = $ranked->first(fn (object $row): bool => ! isset($positions[(int) $row->instrument_id])
                    && ! isset($usedSourceTrades[(int) $row->id]));
                if ($candidate === null) break;
                $positions[(int) $candidate->instrument_id] = $this->openPosition($runId, $candidate, $day, $bars, $strategyPriority);
                $usedSourceTrades[(int) $candidate->id] = true;
            }

            if ($positions === []) continue;
            $candidate = $ranked->first(fn (object $row): bool => ! isset($positions[(int) $row->instrument_id])
                && ! isset($usedSourceTrades[(int) $row->id]));
            if ($candidate === null) {
                if ($strategyPriority === 'rotation_first') $this->closeDuePositions($positions, $completed, $day, $bars, $latest);
                continue;
            }

            $weakestId = collect($positions)->sortBy(function (array $position) use ($latest): float {
                return (float) ($latest[$position['instrument_id']]->ki_score ?? $position['entry_score']);
            })->keys()->first();
            $weakestSnapshot = $latest[(int) $weakestId] ?? null;
            if ($weakestSnapshot === null || (float) $candidate->predicted_return <= (float) $weakestSnapshot->predicted_return) {
                if ($strategyPriority === 'rotation_first') $this->closeDuePositions($positions, $completed, $day, $bars, $latest);
                continue;
            }

            $closed = $this->closePosition($positions[(int) $weakestId], $day, $bars, 'better_forecast', $latest[(int) $weakestId]);
            if ($closed !== null) $completed[] = $closed;
            unset($positions[(int) $weakestId]);
            $positions[(int) $candidate->instrument_id] = $this->openPosition($runId, $candidate, $day, $bars, $strategyPriority);
            $usedSourceTrades[(int) $candidate->id] = true;
            $replacements++;

            if ($strategyPriority === 'rotation_first') {
                $this->closeDuePositions($positions, $completed, $day, $bars, $latest);
            }
        }

        $lastDay = (string) $tradingDays->last();
        foreach ($positions as $position) {
            $closed = $this->closePosition($position, $lastDay, $bars, 'period_end', $latest[$position['instrument_id']] ?? null);
            if ($closed !== null) $completed[] = $closed;
        }

        foreach (array_chunk($completed, 500) as $chunk) {
            DB::table('backtest_strategy_trades')->upsert($chunk,
                ['backtest_run_id', 'backtest_trade_id', 'strategy'],
                ['exit_date', 'entry_price', 'exit_price', 'gross_return', 'max_drawdown', 'metadata', 'updated_at']);
        }

        return [
            'strategy' => self::STRATEGY,
            'trades' => count($completed),
            'replacements' => $replacements,
            'rebalance_days' => $rebalanceDays->count(),
            'interval_trading_days' => 5,
            'strategy_priority' => $strategyPriority,
        ];
    }

    private function openPosition(int $runId, object $snapshot, string $day, Collection $bars, string $strategyPriority): array
    {
        $bar = $this->priceOn($bars->get((int) $snapshot->instrument_id), $day);
        return [
            'run_id' => $runId,
            'source_id' => (int) $snapshot->id,
            'instrument_id' => (int) $snapshot->instrument_id,
            'entry_date' => $day,
            'entry_price' => (float) $bar->open,
            'entry_score' => (float) $snapshot->ki_score,
            'entry_forecast' => (float) $snapshot->predicted_return,
            'planned_exit_date' => $this->plannedExitDate($bars->get((int) $snapshot->instrument_id), $day, 20),
            'strategy_priority' => $strategyPriority,
        ];
    }

    private function closePosition(array $position, string $day, Collection $bars, string $reason, ?object $snapshot): ?array
    {
        $instrumentBars = $bars->get($position['instrument_id']);
        $bar = $this->priceOn($instrumentBars, $day);
        if ($bar === null || $position['entry_price'] <= 0) return null;
        $heldBars = $instrumentBars->filter(fn (object $item, string $date): bool => $date >= $position['entry_date'] && $date <= $day);
        $minimum = (float) ($heldBars->min('low') ?? $position['entry_price']);
        $exitPrice = (float) $bar->close;
        $now = now();
        return [
            'backtest_run_id' => $position['run_id'],
            'backtest_trade_id' => $position['source_id'],
            'instrument_id' => $position['instrument_id'],
            'strategy' => self::STRATEGY,
            'entry_date' => $position['entry_date'],
            'exit_date' => $day,
            'entry_price' => $position['entry_price'],
            'exit_price' => $exitPrice,
            'gross_return' => ($exitPrice / $position['entry_price']) - 1,
            'max_drawdown' => max(0, 1 - ($minimum / $position['entry_price'])),
            'metadata' => json_encode([
                'engine' => 'forecast_score_rotation_v1',
                'rebalance_interval_trading_days' => 5,
                'exit_reason' => $reason,
                'entry_score' => $position['entry_score'],
                'entry_forecast' => $position['entry_forecast'],
                'strategy_priority' => $position['strategy_priority'],
                'exit_score' => $snapshot?->ki_score,
                'exit_forecast' => $snapshot?->predicted_return,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function priceOn(?Collection $bars, string $day): ?object
    {
        if ($bars === null || $bars->isEmpty()) return null;
        return $bars->get($day) ?? $bars->filter(fn (object $bar, string $date): bool => $date <= $day)->last();
    }

    private function plannedExitDate(?Collection $bars, string $entryDay, int $holdingDays): string
    {
        if ($bars === null || $bars->isEmpty()) return $entryDay;
        $dates = $bars->keys()->filter(fn (string $date): bool => $date >= $entryDay)->values();
        return (string) ($dates->get($holdingDays) ?? $dates->last() ?? $entryDay);
    }

    private function closeDuePositions(array &$positions, array &$completed, string $day, Collection $bars, array $latest): void
    {
        foreach ($positions as $instrumentId => $position) {
            if (($position['planned_exit_date'] ?? '9999-12-31') > $day) continue;
            $closed = $this->closePosition($position, $day, $bars, 'selected_exit_strategy', $latest[(int) $instrumentId] ?? null);
            if ($closed !== null) $completed[] = $closed;
            unset($positions[$instrumentId]);
        }
    }

}
