<?php

namespace App\Services\Champion;

use App\Services\ModelComparison\ComparisonResult;
use App\Services\ModelComparison\ModelMetrics;
use DateInterval;
use DateTimeImmutable;

final class PromotionValidator
{
    public const DEFAULT_RULES = [
        'minimum_validated_predictions' => 1000,
        'minimum_selection_score_difference' => 0.02,
        'minimum_direction_accuracy_difference' => 0.01,
        'minimum_strategy_return_difference' => 0.005,
        'maximum_rmse_regression' => 0.0,
        'maximum_stability_regression' => 0.0,
        'cooldown_days' => 30,
    ];

    public function validate(
        int $championModelId,
        int $challengerModelId,
        ModelMetrics $champion,
        ModelMetrics $challenger,
        ComparisonResult $comparison,
        DateTimeImmutable $championActivatedAt,
        ?DateTimeImmutable $decidedAt = null,
        array $rules = [],
        array $metadata = [],
    ): PromotionDecision {
        $decidedAt ??= new DateTimeImmutable();

        $resolvedRules = array_merge(
            self::DEFAULT_RULES,
            $rules,
        );

        $cooldownUntil = $championActivatedAt->add(
            new DateInterval(
                'P'.(int) $resolvedRules['cooldown_days'].'D'
            )
        );

        $checks = [
            'comparison_recommends_promotion' =>
                $comparison->promotionRecommended,

            'challenger_won' =>
                $comparison->winner === 'challenger',

            'minimum_predictions' =>
                $challenger->predictionCount
                >= (int) $resolvedRules[
                    'minimum_validated_predictions'
                ],

            'cooldown_complete' =>
                $decidedAt >= $cooldownUntil,

            'selection_score' =>
                (
                    $comparison->challengerScore->score
                    - $comparison->championScore->score
                )
                >= (float) $resolvedRules[
                    'minimum_selection_score_difference'
                ],

            'direction_accuracy' =>
                (
                    $challenger->directionAccuracy
                    - $champion->directionAccuracy
                )
                >= (float) $resolvedRules[
                    'minimum_direction_accuracy_difference'
                ],

            'strategy_return' =>
                (
                    $challenger->strategyReturn
                    - $champion->strategyReturn
                )
                >= (float) $resolvedRules[
                    'minimum_strategy_return_difference'
                ],

            'rmse_not_worse' =>
                (
                    $challenger->rmse
                    - $champion->rmse
                )
                <= (float) $resolvedRules[
                    'maximum_rmse_regression'
                ],

            'stability_not_worse' =>
                (
                    $champion->stability
                    - $challenger->stability
                )
                <= (float) $resolvedRules[
                    'maximum_stability_regression'
                ],
        ];

        $failedChecks = array_keys(
            array_filter(
                $checks,
                static fn (bool $passed): bool => ! $passed,
            )
        );

        if ($failedChecks === []) {
            return PromotionDecision::approved(
                championModelId: $championModelId,
                challengerModelId: $challengerModelId,
                reason: 'Challenger erfüllt alle Promotion-Regeln.',
                decidedAt: $decidedAt,
                checks: $checks,
                metadata: [
                    ...$metadata,
                    'rules' => $resolvedRules,
                ],
            );
        }

        return PromotionDecision::rejected(
            championModelId: $championModelId,
            challengerModelId: $challengerModelId,
            reason: 'Promotion abgelehnt: '
                .implode(', ', $failedChecks),
            decidedAt: $decidedAt,
            cooldownUntil: (
                $checks['cooldown_complete']
                    ? null
                    : $cooldownUntil
            ),
            checks: $checks,
            metadata: [
                ...$metadata,
                'rules' => $resolvedRules,
                'failed_checks' => $failedChecks,
            ],
        );
    }
}
