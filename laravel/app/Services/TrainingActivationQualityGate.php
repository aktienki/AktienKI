<?php

namespace App\Services;

final class TrainingActivationQualityGate
{
    /** @return array{minimum_direction_accuracy: float, minimum_profit_factor: float, minimum_trade_count: int, reduced_minimum_trade_count: int, reduced_trade_count_minimum_direction_accuracy: float, maximum_drawdown: float} */
    public function rules(): array
    {
        $rules = (array) config('aktienki.training_activation_quality_gate', []);

        return [
            'minimum_direction_accuracy' => (float) ($rules['minimum_direction_accuracy'] ?? 0.55),
            'minimum_profit_factor' => (float) ($rules['minimum_profit_factor'] ?? 1.30),
            'minimum_trade_count' => (int) ($rules['minimum_trade_count'] ?? 15),
            'reduced_minimum_trade_count' => (int) ($rules['reduced_minimum_trade_count'] ?? 10),
            'reduced_trade_count_minimum_direction_accuracy' => (float) ($rules['reduced_trade_count_minimum_direction_accuracy'] ?? 0.65),
            'maximum_drawdown' => (float) ($rules['maximum_drawdown'] ?? 0.40),
        ];
    }

    public function passes(array|string|null $metrics): bool
    {
        if (is_string($metrics)) {
            $metrics = json_decode($metrics, true);
        }
        if (! is_array($metrics)) {
            return false;
        }

        $rules = $this->rules();
        $directionAccuracy = $metrics['direction_accuracy'] ?? null;
        $profitFactor = $metrics['profit_factor'] ?? null;
        $tradeCount = $metrics['trade_count'] ?? null;
        $maximumDrawdown = $metrics['max_drawdown'] ?? $metrics['maximum_drawdown'] ?? null;

        foreach ([$directionAccuracy, $profitFactor, $tradeCount, $maximumDrawdown] as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                return false;
            }
        }

        $requiredTradeCount = (float) $directionAccuracy >= $rules['reduced_trade_count_minimum_direction_accuracy']
            ? $rules['reduced_minimum_trade_count']
            : $rules['minimum_trade_count'];

        return (float) $directionAccuracy >= $rules['minimum_direction_accuracy']
            && (float) $profitFactor >= $rules['minimum_profit_factor']
            && (int) $tradeCount >= $requiredTradeCount
            && abs((float) $maximumDrawdown) <= $rules['maximum_drawdown'];
    }
}
