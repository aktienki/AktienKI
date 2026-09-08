<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class HistoricalActionScoreService
{
    public const VERSION = 'historical-action-v3-fully-non-overlapping';

    public function __construct(
        private readonly ActionScoreFormula $formula,
        private readonly MacdStochasticMarketPhaseService $marketPhaseService,
    ) {}

    /**
     * Recalculate every walk-forward row chronologically. Only outcomes whose
     * exit predates the current signal are evidence, preventing look-ahead.
     */
    public function score(Collection $rows): Collection
    {
        return $rows->groupBy('instrument_id')->flatMap(function (Collection $instrumentRows): Collection {
            $ordered = $instrumentRows->sortBy(fn (object $row): string => (string) $row->signal_date)->values();
            $evidence = $this->executableEvidence($ordered);
            $cursor = 0;
            $state = [
                'count' => 0, 'sum' => 0.0, 'wins' => 0,
                'gross_profit' => 0.0, 'gross_loss' => 0.0,
                'equity' => 1.0, 'peak' => 1.0, 'drawdown' => null,
                'years' => [],
            ];

            return $ordered->map(function (object $row) use ($evidence, &$cursor, &$state): object {
                $signalDate = CarbonImmutable::parse($row->signal_date);
                while ($cursor < $evidence->count() && CarbonImmutable::parse($evidence[$cursor]->exit_date)->lt($signalDate)) {
                    $this->addEvidence($state, $evidence[$cursor]);
                    $cursor++;
                }
                $historicalProfitFactor = $state['gross_loss'] > 0
                    ? $state['gross_profit'] / $state['gross_loss']
                    : ($state['gross_profit'] > 0 ? 3.0 : null);
                $profitFactor = $historicalProfitFactor;
                $averageTrade = $state['count'] > 0 ? ($state['sum'] / $state['count']) * 100 : null;
                $historicalHitRate = $state['count'] > 0 ? ($state['wins'] / $state['count']) * 100 : null;
                $hitRate = $historicalHitRate;
                $tradeCount = $state['count'];
                $drawdown = $state['drawdown'];
                $stability = $state['years'] !== []
                    ? (count(array_filter($state['years'], fn (float $return): bool => $return > 0)) / count($state['years'])) * 100
                    : null;
                $confidence = $historicalHitRate ?? 0.0;
                $expectedReturn = ((float) ($row->predicted_return ?? 0) * 100)
                    - max(0.0, (float) config('aktienki.signals.round_trip_cost_percent', .5));
                $qualityGatePassed = $tradeCount >= 10 && $averageTrade !== null && $averageTrade >= 0
                    && $profitFactor !== null && $profitFactor >= 1.05;
                $marketPhase = $this->marketPhase($row);

                $result = $this->formula->calculate([
                    'profit_factor' => $profitFactor,
                    'average_trade' => $averageTrade,
                    'confidence' => $confidence,
                    'expected_return' => $expectedReturn,
                    'drawdown' => $drawdown,
                    'hit_rate' => $hitRate,
                    'stability' => $stability,
                    'trade_count' => $tradeCount,
                    'quality_gate_passed' => $qualityGatePassed,
                    'hard_blockers' => [],
                ], $marketPhase);

                $row->historical_action_score = $result['score'];
                $row->historical_action_signal = $result['signal'];
                $row->historical_action_components = [
                    'version' => self::VERSION,
                    'point_in_time' => true,
                    'evidence_cutoff' => $signalDate->subDay()->toDateString(),
                    'values' => $result['values'],
                    'weights' => $result['weights'],
                    'metrics' => compact('profitFactor', 'averageTrade', 'hitRate', 'tradeCount', 'expectedReturn', 'drawdown', 'stability'),
                    'market_phase' => $marketPhase,
                    'blocked' => $result['blocked'],
                    'execution_policy' => [
                        'one_position_per_instrument' => true,
                        'held_through_horizon_exit_date' => true,
                        'same_exit_date_reentry_allowed' => false,
                        'overlapping_candidates_used_in_metrics' => false,
                    ],
                ];

                return $row;
            });
        })->sortBy(fn (object $row): string => (string) $row->signal_date)->values();
    }

    private function executableEvidence(Collection $ordered): Collection
    {
        $positionExit = null;

        return $ordered
            ->filter(fn (object $row): bool => filled($row->exit_date) && is_numeric($row->net_return ?? null))
            ->sort(function (object $left, object $right): int {
                return strcmp((string) $left->signal_date, (string) $right->signal_date)
                    ?: ((float) ($right->predicted_return ?? 0) <=> (float) ($left->predicted_return ?? 0))
                    ?: ((int) ($left->trade_id ?? 0) <=> (int) ($right->trade_id ?? 0));
            })
            ->filter(function (object $row) use (&$positionExit): bool {
                $entry = CarbonImmutable::parse($row->signal_date);
                $exit = CarbonImmutable::parse($row->exit_date);
                if ($exit->lt($entry)) {
                    return false;
                }
                // Daily-close execution keeps the instrument occupied on the
                // sale date itself. The next entry must be on a later day.
                if ($positionExit !== null && $entry->lte($positionExit)) {
                    return false;
                }
                $positionExit = $exit;

                return true;
            })
            ->sortBy(fn (object $row): string => (string) $row->exit_date)
            ->values();
    }

    private function addEvidence(array &$state, object $row): void
    {
        $return = max(-0.999999, (float) $row->net_return);
        $state['count']++;
        $state['sum'] += $return;
        $state['wins'] += $return > 0 ? 1 : 0;
        $state['gross_profit'] += $return > 0 ? $return : 0;
        $state['gross_loss'] += $return < 0 ? abs($return) : 0;
        $state['equity'] *= 1 + $return;
        $state['peak'] = max($state['peak'], $state['equity']);
        $currentDrawdown = $state['peak'] > 0 ? (($state['peak'] - $state['equity']) / $state['peak']) * 100 : 0;
        $state['drawdown'] = max((float) ($state['drawdown'] ?? 0), $currentDrawdown);
        $year = CarbonImmutable::parse($row->exit_date)->format('Y');
        $state['years'][$year] = (float) ($state['years'][$year] ?? 0) + $return;
    }

    private function marketPhase(object $row): ?array
    {
        $points = is_array($row->market_phase_points ?? null)
            ? $row->market_phase_points
            : (json_decode((string) ($row->market_phase_points ?? '[]'), true) ?: []);
        if (count($points) < 2) {
            return null;
        }

        return $this->marketPhaseService->classify(
            (float) $points[0]['macd'],
            (float) $points[1]['macd'],
            (float) $points[0]['stochastic'],
            (float) $points[1]['stochastic'],
        );
    }

}
