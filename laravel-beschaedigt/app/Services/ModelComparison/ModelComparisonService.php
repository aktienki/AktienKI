<?php

namespace App\Services\ModelComparison;

use App\Services\Elo\EloRatingService;
use App\Services\Elo\EloCalculator;

final class ModelComparisonService
{
    public function __construct(
        private readonly SelectionScoreCalculator $scoreCalculator,
        private readonly EloRatingService $eloRatingService,
    ) {
    }

    public static function make(): self
    {
        return new self(
            new SelectionScoreCalculator(),
            new EloRatingService(
                new EloCalculator()
            ),
        );
    }

    public function compare(
        ModelMetrics $champion,
        ModelMetrics $challenger,
        array $rules = [],
        array $weights = SelectionScoreCalculator::DEFAULT_WEIGHTS,
    ): ComparisonResult {
        $resolvedRules = $this->resolveRules($rules);

        $championScore = $this->scoreCalculator->calculate(
            directionAccuracy: $champion->directionAccuracy,
            strategyReturn: $champion->strategyReturn,
            rmse: $champion->rmse,
            stability: $champion->stability,
            predictionCount: $champion->predictionCount,
            weights: $weights,
        );

        $challengerScore = $this->scoreCalculator->calculate(
            directionAccuracy: $challenger->directionAccuracy,
            strategyReturn: $challenger->strategyReturn,
            rmse: $challenger->rmse,
            stability: $challenger->stability,
            predictionCount: $challenger->predictionCount,
            weights: $weights,
        );

        $winner = $this->determineWinner(
            championScore: $championScore->score,
            challengerScore: $challengerScore->score,
        );

        $eloResult = $this->eloRatingService->update(
            championRating: $champion->eloRating,
            challengerRating: $challenger->eloRating,
            winner: $winner,
            kFactor: (int) $resolvedRules['elo_k_factor'],
        );

        [$promotionRecommended, $reasons] =
            $this->evaluatePromotion(
                champion: $champion,
                challenger: $challenger,
                championScore: $championScore->score,
                challengerScore: $challengerScore->score,
                winner: $winner,
                rules: $resolvedRules,
            );

        return new ComparisonResult(
            winner: $winner,
            championScore: $championScore,
            challengerScore: $challengerScore,
            eloResult: $eloResult,
            promotionRecommended: $promotionRecommended,
            reasons: $reasons,
            rules: $resolvedRules,
        );
    }

    private function determineWinner(
        float $championScore,
        float $challengerScore,
    ): string {
        $difference = $challengerScore - $championScore;

        if (abs($difference) < 0.000001) {
            return EloCalculator::RESULT_DRAW;
        }

        return $difference > 0
            ? EloCalculator::RESULT_CHALLENGER
            : EloCalculator::RESULT_CHAMPION;
    }

    private function evaluatePromotion(
        ModelMetrics $champion,
        ModelMetrics $challenger,
        float $championScore,
        float $challengerScore,
        string $winner,
        array $rules,
    ): array {
        $reasons = [];

        if ($winner !== EloCalculator::RESULT_CHALLENGER) {
            $reasons[] = 'Der Challenger hat den Vergleich nicht gewonnen.';

            return [false, $reasons];
        }

        $minimumPredictions = (int)
            $rules['minimum_validated_predictions'];

        if ($challenger->predictionCount < $minimumPredictions) {
            $reasons[] = sprintf(
                'Zu wenige validierte Predictions: %d von mindestens %d.',
                $challenger->predictionCount,
                $minimumPredictions,
            );
        }

        $selectionDifference = (
            $challengerScore - $championScore
        );

        if (
            $selectionDifference
            < (float) $rules['minimum_selection_score_difference']
        ) {
            $reasons[] = sprintf(
                'Selection-Score-Vorsprung %.6f liegt unter %.6f.',
                $selectionDifference,
                (float) $rules[
                    'minimum_selection_score_difference'
                ],
            );
        }

        $directionDifference = (
            $challenger->directionAccuracy
            - $champion->directionAccuracy
        );

        if (
            $directionDifference
            < (float) $rules[
                'minimum_direction_accuracy_difference'
            ]
        ) {
            $reasons[] = sprintf(
                'Direction-Accuracy-Vorsprung %.6f liegt unter %.6f.',
                $directionDifference,
                (float) $rules[
                    'minimum_direction_accuracy_difference'
                ],
            );
        }

        $returnDifference = (
            $challenger->strategyReturn
            - $champion->strategyReturn
        );

        if (
            $returnDifference
            < (float) $rules[
                'minimum_strategy_return_difference'
            ]
        ) {
            $reasons[] = sprintf(
                'Strategy-Return-Vorsprung %.6f liegt unter %.6f.',
                $returnDifference,
                (float) $rules[
                    'minimum_strategy_return_difference'
                ],
            );
        }

        if ($reasons !== []) {
            return [false, $reasons];
        }

        return [
            true,
            [
                'Der Challenger erfüllt alle Voraussetzungen '
                .'für eine Promotion.',
            ],
        ];
    }

    private function resolveRules(array $rules): array
    {
        $defaults = [
            'minimum_validated_predictions' => 1000,
            'minimum_selection_score_difference' => 0.02,
            'minimum_direction_accuracy_difference' => 0.01,
            'minimum_strategy_return_difference' => 0.005,
            'elo_k_factor' => 32,
        ];

        return array_merge($defaults, $rules);
    }
}
