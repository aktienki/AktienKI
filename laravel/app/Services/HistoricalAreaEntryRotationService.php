<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HistoricalAreaEntryRotationService
{
    public const SECTOR_STRATEGY = 'sector_entry_rotation_20d';
    public const INDEX_STRATEGY = 'index_entry_rotation_20d';
    private const FORECAST_TOLERANCE = 0.02;

    public function apply(int $runId, bool $sectorEnabled, bool $indexEnabled, string $riskStyle = 'balanced'): array
    {
        DB::table('backtest_strategy_trades')->where('backtest_run_id', $runId)
            ->whereIn('strategy', [self::SECTOR_STRATEGY, self::INDEX_STRATEGY])->delete();

        $trades = DB::table('backtest_trades as trade')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->where('trade.backtest_run_id', $runId)
            ->whereNotNull('trade.predicted_return')
            ->where('trade.predicted_return', '>', 0)
            ->whereBetween('trade.gross_return', [-1.0, 3.0])
            ->get(['trade.*', 'instrument.sector']);

        $instrumentMetrics = $trades->groupBy('instrument_id')->map(function (Collection $rows): array {
            $wins = $rows->filter(fn (object $trade): bool => (float) $trade->net_return > 0);
            $losses = $rows->filter(fn (object $trade): bool => (float) $trade->net_return < 0);
            $grossProfit = (float) $wins->sum('net_return');
            $grossLoss = abs((float) $losses->sum('net_return'));

            return [
                'drawdown' => (float) $rows->max(fn (object $trade): float => abs((float) ($trade->max_drawdown ?? 0))),
                'hit_rate' => $rows->isNotEmpty() ? $wins->count() / $rows->count() : 0.0,
                'profit_factor' => $grossLoss > 0 ? $grossProfit / $grossLoss : ($grossProfit > 0 ? INF : 0.0),
            ];
        });
        $trades->each(function (object $trade) use ($instrumentMetrics): void {
            $metrics = $instrumentMetrics->get($trade->instrument_id, ['drawdown' => 0.0, 'hit_rate' => 0.0, 'profit_factor' => 0.0]);
            $trade->selection_drawdown = $metrics['drawdown'];
            $trade->selection_hit_rate = $metrics['hit_rate'];
            $trade->selection_profit_factor = $metrics['profit_factor'];
        });

        $memberships = $indexEnabled && $trades->isNotEmpty()
            ? DB::table('index_memberships')->whereNull('removed_at')
                ->whereIn('instrument_id', $trades->pluck('instrument_id')->unique())
                ->get(['instrument_id', 'market_index_id'])->groupBy('instrument_id')
            : collect();

        $summary = ['forecast_tolerance_percentage_points' => 2.0];
        if ($sectorEnabled) $summary['sector'] = $this->persist($runId, $trades, self::SECTOR_STRATEGY, $memberships, $riskStyle);
        if ($indexEnabled) $summary['index'] = $this->persist($runId, $trades, self::INDEX_STRATEGY, $memberships, $riskStyle);

        return $summary;
    }

    private function persist(int $runId, Collection $trades, string $strategy, Collection $memberships, string $riskStyle): array
    {
        $ordered = $trades->groupBy('entry_date')->flatMap(function (Collection $daily) use ($strategy, $memberships, $riskStyle): Collection {
            $bestForecast = (float) $daily->max('predicted_return');
            $preferredSector = null;
            $preferredIndex = null;

            if ($strategy === self::SECTOR_STRATEGY) {
                $preferredSector = $daily->filter(fn (object $trade): bool => filled($trade->sector))
                    ->groupBy('sector')->map(fn (Collection $rows): float => (float) $rows->avg('ki_score'))
                    ->sortDesc()->keys()->first();
            } else {
                $indexScores = collect();
                foreach ($daily as $trade) {
                    foreach ($memberships->get($trade->instrument_id, collect()) as $membership) {
                        $indexScores->push(['index' => (int) $membership->market_index_id, 'score' => (float) $trade->ki_score]);
                    }
                }
                $preferredIndex = $indexScores->groupBy('index')->map(fn (Collection $rows): float => (float) $rows->avg('score'))
                    ->sortDesc()->keys()->first();
            }

            return $daily->map(function (object $trade) use ($strategy, $memberships, $preferredSector, $preferredIndex, $bestForecast): object {
                $areaMatch = $strategy === self::SECTOR_STRATEGY
                    ? $preferredSector !== null && $trade->sector === $preferredSector
                    : $preferredIndex !== null && $memberships->get($trade->instrument_id, collect())
                        ->contains(fn (object $membership): bool => (int) $membership->market_index_id === (int) $preferredIndex);
                $trade->area_preferred = $areaMatch && ($bestForecast - (float) $trade->predicted_return) <= self::FORECAST_TOLERANCE;
                return $trade;
            })->sort(fn (object $left, object $right): int => ((int) $right->area_preferred <=> (int) $left->area_preferred)
                ?: $this->compareByRiskStyle($left, $right, $riskStyle))->values();
        })->values();

        $now = now();
        $preferred = 0;
        foreach ($ordered->chunk(500) as $chunk) {
            DB::table('backtest_strategy_trades')->insert($chunk->map(function (object $trade) use ($runId, $strategy, $riskStyle, $now, &$preferred): array {
                if ($trade->area_preferred) $preferred++;
                return [
                    'backtest_run_id' => $runId,
                    'backtest_trade_id' => $trade->id,
                    'instrument_id' => $trade->instrument_id,
                    'strategy' => $strategy,
                    'entry_date' => $trade->entry_date,
                    'exit_date' => $trade->exit_date,
                    'entry_price' => $trade->entry_price,
                    'exit_price' => $trade->exit_price,
                    'gross_return' => $trade->gross_return,
                    'max_drawdown' => $trade->max_drawdown,
                    'metadata' => json_encode([
                        'engine' => 'area_entry_rotation_v1',
                        'area_preferred' => (bool) $trade->area_preferred,
                        'forecast_tolerance_percentage_points' => 2.0,
                        'forecast_20d' => (float) $trade->predicted_return,
                        'selection_profile' => $riskStyle,
                        'selection_drawdown' => (float) $trade->selection_drawdown,
                        'selection_hit_rate' => (float) $trade->selection_hit_rate,
                        'selection_profit_factor' => is_finite((float) $trade->selection_profit_factor) ? (float) $trade->selection_profit_factor : null,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all());
        }

        return ['strategy' => $strategy, 'candidates' => $ordered->count(), 'preferred_candidates' => $preferred];
    }

    private function compareByRiskStyle(object $left, object $right, string $riskStyle): int
    {
        $drawdown = fn (object $trade): float => (float) ($trade->selection_drawdown ?? 0);
        $hitRate = fn (object $trade): float => (float) ($trade->selection_hit_rate ?? 0);
        $profitFactor = fn (object $trade): float => (float) ($trade->selection_profit_factor ?? 0);

        return match ($riskStyle) {
            'conservative' => ($drawdown($left) <=> $drawdown($right))
                ?: ($hitRate($right) <=> $hitRate($left))
                ?: ($profitFactor($right) <=> $profitFactor($left)),
            'chance' => ($profitFactor($right) <=> $profitFactor($left))
                ?: ($hitRate($right) <=> $hitRate($left))
                ?: ($drawdown($left) <=> $drawdown($right)),
            default => ($hitRate($right) <=> $hitRate($left))
                ?: ($profitFactor($right) <=> $profitFactor($left))
                ?: ($drawdown($left) <=> $drawdown($right)),
        };
    }
}
