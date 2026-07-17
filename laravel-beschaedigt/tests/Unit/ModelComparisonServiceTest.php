<?php

namespace Tests\Unit;

use App\Services\Elo\EloCalculator;
use App\Services\Elo\EloRatingService;
use App\Services\ModelComparison\ModelComparisonService;
use App\Services\ModelComparison\ModelMetrics;
use App\Services\ModelComparison\SelectionScoreCalculator;
use Tests\TestCase;

class ModelComparisonServiceTest extends TestCase
{
    private ModelComparisonService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ModelComparisonService(
            new SelectionScoreCalculator(),
            new EloRatingService(
                new EloCalculator()
            ),
        );
    }

    public function test_challenger_wins_with_better_metrics(): void
    {
        $result = $this->service->compare(
            champion: new ModelMetrics(
                directionAccuracy: 0.58,
                strategyReturn: 0.03,
                rmse: 0.03,
                stability: 0.75,
                predictionCount: 1200,
                eloRating: 1550,
            ),
            challenger: new ModelMetrics(
                directionAccuracy: 0.65,
                strategyReturn: 0.08,
                rmse: 0.02,
                stability: 0.92,
                predictionCount: 1200,
                eloRating: 1500,
            ),
            rules: [
                'minimum_validated_predictions' => 1000,
                'minimum_selection_score_difference' => 0.02,
                'minimum_direction_accuracy_difference' => 0.01,
                'minimum_strategy_return_difference' => 0.005,
                'elo_k_factor' => 32,
            ],
        );

        $this->assertSame('challenger', $result->winner);
        $this->assertTrue($result->promotionRecommended);
        $this->assertGreaterThan(
            $result->championScore->score,
            $result->challengerScore->score,
        );
        $this->assertGreaterThan(
            1500.0,
            $result->eloResult->challengerAfter,
        );
    }

    public function test_champion_wins_with_better_metrics(): void
    {
        $result = $this->service->compare(
            champion: new ModelMetrics(
                directionAccuracy: 0.66,
                strategyReturn: 0.09,
                rmse: 0.018,
                stability: 0.95,
                predictionCount: 1500,
                eloRating: 1600,
            ),
            challenger: new ModelMetrics(
                directionAccuracy: 0.57,
                strategyReturn: 0.02,
                rmse: 0.04,
                stability: 0.70,
                predictionCount: 1500,
                eloRating: 1500,
            ),
            rules: [
                'minimum_validated_predictions' => 1000,
                'minimum_selection_score_difference' => 0.02,
                'minimum_direction_accuracy_difference' => 0.01,
                'minimum_strategy_return_difference' => 0.005,
                'elo_k_factor' => 32,
            ],
        );

        $this->assertSame('champion', $result->winner);
        $this->assertFalse($result->promotionRecommended);
    }

    public function test_no_promotion_with_too_few_predictions(): void
    {
        $result = $this->service->compare(
            champion: new ModelMetrics(
                directionAccuracy: 0.58,
                strategyReturn: 0.03,
                rmse: 0.03,
                stability: 0.75,
                predictionCount: 1200,
                eloRating: 1550,
            ),
            challenger: new ModelMetrics(
                directionAccuracy: 0.65,
                strategyReturn: 0.08,
                rmse: 0.02,
                stability: 0.92,
                predictionCount: 200,
                eloRating: 1500,
            ),
            rules: [
                'minimum_validated_predictions' => 1000,
                'minimum_selection_score_difference' => 0.02,
                'minimum_direction_accuracy_difference' => 0.01,
                'minimum_strategy_return_difference' => 0.005,
                'elo_k_factor' => 32,
            ],
        );

        $this->assertSame('challenger', $result->winner);
        $this->assertFalse($result->promotionRecommended);

        $this->assertStringContainsString(
            'Zu wenige validierte Predictions',
            implode(' ', $result->reasons),
        );
    }

    public function test_no_promotion_when_score_advantage_is_too_small(): void
    {
        $result = $this->service->compare(
            champion: new ModelMetrics(
                directionAccuracy: 0.60,
                strategyReturn: 0.05,
                rmse: 0.025,
                stability: 0.85,
                predictionCount: 1500,
                eloRating: 1500,
            ),
            challenger: new ModelMetrics(
                directionAccuracy: 0.601,
                strategyReturn: 0.0505,
                rmse: 0.0249,
                stability: 0.851,
                predictionCount: 1500,
                eloRating: 1500,
            ),
            rules: [
                'minimum_validated_predictions' => 1000,
                'minimum_selection_score_difference' => 0.05,
                'minimum_direction_accuracy_difference' => 0.0,
                'minimum_strategy_return_difference' => 0.0,
                'elo_k_factor' => 32,
            ],
        );

        $this->assertSame('challenger', $result->winner);
        $this->assertFalse($result->promotionRecommended);
    }

    public function test_equal_scores_result_in_draw(): void
    {
        $metrics = new ModelMetrics(
            directionAccuracy: 0.60,
            strategyReturn: 0.05,
            rmse: 0.025,
            stability: 0.85,
            predictionCount: 1500,
            eloRating: 1500,
        );

        $result = $this->service->compare(
            champion: $metrics,
            challenger: $metrics,
            rules: [
                'minimum_validated_predictions' => 1000,
                'minimum_selection_score_difference' => 0.02,
                'minimum_direction_accuracy_difference' => 0.01,
                'minimum_strategy_return_difference' => 0.005,
                'elo_k_factor' => 32,
            ],
        );

        $this->assertSame('draw', $result->winner);
        $this->assertFalse($result->promotionRecommended);
        $this->assertSame(
            1500.0,
            $result->eloResult->championAfter,
        );
        $this->assertSame(
            1500.0,
            $result->eloResult->challengerAfter,
        );
    }

    public function test_result_can_be_serialized(): void
    {
        $result = $this->service->compare(
            champion: new ModelMetrics(
                directionAccuracy: 0.58,
                strategyReturn: 0.03,
                rmse: 0.03,
                stability: 0.75,
                predictionCount: 1200,
                eloRating: 1550,
            ),
            challenger: new ModelMetrics(
                directionAccuracy: 0.65,
                strategyReturn: 0.08,
                rmse: 0.02,
                stability: 0.92,
                predictionCount: 1200,
                eloRating: 1500,
            ),
            rules: [
                'minimum_validated_predictions' => 1000,
                'minimum_selection_score_difference' => 0.02,
                'minimum_direction_accuracy_difference' => 0.01,
                'minimum_strategy_return_difference' => 0.005,
                'elo_k_factor' => 32,
            ],
        )->toArray();

        $this->assertArrayHasKey('winner', $result);
        $this->assertArrayHasKey('champion_score', $result);
        $this->assertArrayHasKey('challenger_score', $result);
        $this->assertArrayHasKey('elo_result', $result);
        $this->assertArrayHasKey(
            'promotion_recommended',
            $result,
        );
        $this->assertArrayHasKey('reasons', $result);
    }
}
