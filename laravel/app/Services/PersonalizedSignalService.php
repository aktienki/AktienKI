<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

class PersonalizedSignalService
{
    /**
     * Build the SQL expression used everywhere a user-facing signal is needed.
     * The model's original prediction.signal remains untouched for auditing.
     */
    public function sql(string $predictionAlias = 'prediction', ?User $user = null): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $predictionAlias)) {
            throw new InvalidArgumentException('Invalid prediction table alias.');
        }

        $thresholds = $this->thresholds($this->riskLevel($user));
        $score = "(CASE WHEN {$predictionAlias}.prediction_score <= 1 THEN {$predictionAlias}.prediction_score * 100 WHEN {$predictionAlias}.prediction_score <= 10 THEN {$predictionAlias}.prediction_score * 10 ELSE {$predictionAlias}.prediction_score END)";
        $confidence = "(CASE WHEN {$predictionAlias}.confidence > 1 THEN {$predictionAlias}.confidence / 100 ELSE {$predictionAlias}.confidence END)";
        $riskSource = "COALESCE({$predictionAlias}.risk_score, {$predictionAlias}.drawdown_risk_factor)";
        $risk = "(CASE WHEN {$riskSource} > 1 THEN {$riskSource} / 100 ELSE {$riskSource} END)";
        $return = "(({$predictionAlias}.predicted_price_5d - {$predictionAlias}.current_price) / NULLIF({$predictionAlias}.current_price, 0) * 100)";

        return <<<SQL
            CASE
                WHEN {$predictionAlias}.id IS NULL THEN 'HOLD'
                WHEN {$score} < {$thresholds['sell_score']}
                    OR ({$return} IS NOT NULL AND {$return} <= {$thresholds['sell_return']})
                    THEN 'SELL'
                WHEN {$score} >= {$thresholds['buy_score']}
                    AND COALESCE({$confidence}, 0) >= {$thresholds['buy_confidence']}
                    AND ({$risk} IS NULL OR {$risk} <= {$thresholds['buy_risk']})
                    AND ({$return} IS NULL OR {$return} >= {$thresholds['buy_return']})
                    THEN 'BUY'
                WHEN {$score} >= {$thresholds['watch_score']}
                    AND COALESCE({$confidence}, 0) >= {$thresholds['watch_confidence']}
                    AND ({$risk} IS NULL OR {$risk} <= {$thresholds['watch_risk']})
                    AND ({$return} IS NULL OR {$return} >= {$thresholds['watch_return']})
                    THEN 'WATCH'
                ELSE 'HOLD'
            END
        SQL;
    }

    public function riskLevel(?User $user = null): string
    {
        $level = (string) data_get($user?->meta, 'risk_profile.level', 'normal');

        return match ($level) {
            'cautious', 'conservative' => 'cautious',
            'opportunity_oriented', 'opportunity', 'aggressive' => 'opportunity_oriented',
            default => 'normal',
        };
    }

    private function thresholds(string $level): array
    {
        return match ($level) {
            'cautious' => [
                'sell_score' => 40,
                'sell_return' => -2.5,
                'buy_score' => 68,
                'buy_confidence' => 0.65,
                'buy_risk' => 0.35,
                'buy_return' => 0,
                'watch_score' => 55,
                'watch_confidence' => 0.50,
                'watch_risk' => 0.55,
                'watch_return' => -1,
            ],
            'opportunity_oriented' => [
                'sell_score' => 32,
                'sell_return' => -4,
                'buy_score' => 57,
                'buy_confidence' => 0.45,
                'buy_risk' => 0.80,
                'buy_return' => -0.5,
                'watch_score' => 46,
                'watch_confidence' => 0.30,
                'watch_risk' => 0.90,
                'watch_return' => -2.5,
            ],
            default => [
                'sell_score' => 36,
                'sell_return' => -3,
                'buy_score' => 62,
                'buy_confidence' => 0.55,
                'buy_risk' => 0.60,
                'buy_return' => 0,
                'watch_score' => 50,
                'watch_confidence' => 0.40,
                'watch_risk' => 0.75,
                'watch_return' => -1.5,
            ],
        };
    }
}
