<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class HistoricalActionScoreService
{
    public const VERSION = 'historical-action-v2-non-overlapping-drawdown';

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
            $evidence = $ordered->filter(fn (object $row): bool => filled($row->exit_date) && is_numeric($row->net_return ?? null))
                ->sortBy(fn (object $row): string => (string) $row->exit_date)->values();
            $cursor = 0;
            $state = [
                'count' => 0, 'sum' => 0.0, 'wins' => 0,
                'gross_profit' => 0.0, 'gross_loss' => 0.0,
                'equity' => 1.0, 'peak' => 1.0, 'drawdown' => null,
                'drawdown_position_exit' => null,
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
                $validationProfitFactor = is_numeric($row->validation_profit_factor ?? null) ? (float) $row->validation_profit_factor : null;
                $profitFactor = $validationProfitFactor ?? $historicalProfitFactor;
                $averageTrade = $state['count'] > 0 ? ($state['sum'] / $state['count']) * 100 : null;
                $historicalHitRate = $state['count'] > 0 ? ($state['wins'] / $state['count']) * 100 : null;
                $validationHitRate = is_numeric($row->validation_direction_accuracy ?? null)
                    ? (float) $row->validation_direction_accuracy * ((float) $row->validation_direction_accuracy <= 1 ? 100 : 1)
                    : null;
                $hitRate = $validationHitRate ?? $historicalHitRate;
                $tradeCount = max($state['count'], (int) ($row->validation_trade_count ?? 0));
                $drawdown = $state['drawdown'];
                $stability = $state['years'] !== []
                    ? (count(array_filter($state['years'], fn (float $return): bool => $return > 0)) / count($state['years'])) * 100
                    : null;
                $confidence = $validationHitRate ?? 0.0;
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
                ];

                return $row;
            });
        })->sortBy(fn (object $row): string => (string) $row->signal_date)->values();
    }

    private function addEvidence(array &$state, object $row): void
    {
        $return = max(-0.999999, (float) $row->net_return);
        $state['count']++;
        $state['sum'] += $return;
        $state['wins'] += $return > 0 ? 1 : 0;
        $state['gross_profit'] += $return > 0 ? $return : 0;
        $state['gross_loss'] += $return < 0 ? abs($return) : 0;
        // Walk-forward rows contain a signal on almost every trading day and
        // therefore overlap for a 20-day horizon. Compounding all of them as
        // sequential positions creates a fictitious ~100% drawdown. Build the
        // drawdown curve only from trades that could actually follow each
        // other without overlapping.
        $entryDate = CarbonImmutable::parse($row->signal_date);
        if ($state['drawdown_position_exit'] === null || $entryDate->gte($state['drawdown_position_exit'])) {
            $state['equity'] *= 1 + $return;
            $state['peak'] = max($state['peak'], $state['equity']);
            $currentDrawdown = $state['peak'] > 0 ? (($state['peak'] - $state['equity']) / $state['peak']) * 100 : 0;
            $state['drawdown'] = max((float) ($state['drawdown'] ?? 0), $currentDrawdown);
            $state['drawdown_position_exit'] = CarbonImmutable::parse($row->exit_date);
        }
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
