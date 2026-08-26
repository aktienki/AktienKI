<?php

namespace App\Services;

use App\Support\ProfitFactor;

final class ActionScoreFormula
{
    /** @return array{score:float,signal:string,blocked:bool,values:array<string,float>,weights:array<string,int>} */
    public function calculate(array $metrics, ?array $marketPhase = null): array
    {
        $profitFactor = ProfitFactor::cap($metrics['profit_factor'] ?? null);
        $averageTrade = is_numeric($metrics['average_trade'] ?? null) ? (float) $metrics['average_trade'] : null;
        $hitRate = is_numeric($metrics['hit_rate'] ?? null) ? (float) $metrics['hit_rate'] : null;
        $drawdown = is_numeric($metrics['drawdown'] ?? null) ? abs((float) $metrics['drawdown']) : null;
        $stability = is_numeric($metrics['stability'] ?? null) ? (float) $metrics['stability'] : null;
        $confidence = max(0, min(100, (float) ($metrics['confidence'] ?? 0)));
        $expectedReturn = (float) ($metrics['expected_return'] ?? 0);
        $tradeCount = max(0, (int) ($metrics['trade_count'] ?? 0));
        $qualityGatePassed = ($metrics['quality_gate_passed'] ?? false) === true;
        $hardBlockers = array_values((array) ($metrics['hard_blockers'] ?? []));

        $values = [
            'profit_factor' => $profitFactor !== null ? max(0, min(100, (($profitFactor - .5) / 2) * 100)) : 0,
            'average_profit_per_trade' => $averageTrade !== null ? max(0, min(100, 50 + ($averageTrade * 12.5))) : 0,
            'confidence' => $confidence,
            'expected_return_20d' => max(0, min(100, 50 + ($expectedReturn * 5))),
            'drawdown' => $drawdown !== null ? max(0, min(100, 100 - ($drawdown * 2))) : 0,
            'hit_rate' => $hitRate !== null ? max(0, min(100, $hitRate)) : 0,
            'stability' => $stability !== null ? max(0, min(100, $stability)) : 0,
            'quality_gate' => $qualityGatePassed ? 100 : 0,
        ];
        $weights = ['profit_factor' => 20, 'average_profit_per_trade' => 10, 'confidence' => 20, 'expected_return_20d' => 15, 'drawdown' => 15, 'hit_rate' => 10, 'stability' => 5, 'quality_gate' => 5];
        $score = round(collect($values)->sum(fn (float $value, string $key): float => $value * $weights[$key]) / 100, 2);
        if ($marketPhase !== null) {
            $score = round(max(0, min(100, $score + (float) ($marketPhase['score_adjustment'] ?? 0))), 2);
        }

        $blocked = $tradeCount < 10 || $averageTrade === null || $averageTrade < 0 || ! $qualityGatePassed || $hardBlockers !== [];
        if ($blocked || (($marketPhase['buy_veto'] ?? false) === true)) {
            $score = min(64.0, $score);
        }

        $signal = match (true) {
            $score >= 65 => 'BUY',
            $score >= 55 => 'WATCH',
            $score >= 40 => 'HOLD',
            default => 'SELL',
        };

        return compact('score', 'signal', 'blocked', 'values', 'weights');
    }
}
