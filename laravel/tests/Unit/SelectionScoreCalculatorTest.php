<?php

namespace Tests\Unit;

use App\Services\ModelComparison\SelectionScoreCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SelectionScoreCalculatorTest extends TestCase
{
    private SelectionScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new SelectionScoreCalculator();
    }

    public function test_it_calculates_a_selection_score(): void
    {
        $result = $this->calculator->calculate(
            directionAccuracy: 0.64,
            strategyReturn: 0.08,
            rmse: 0.02,
            stability: 0.94,
            predictionCount: 1325,
        );

        $this->assertGreaterThan(0.0, $result->score);
        $this->assertLessThanOrEqual(1.0, $result->score);
        $this->assertArrayHasKey(
            'direction_accuracy',
            $result->normalizedMetrics,
        );
    }

    public function test_direction_accuracy_below_random_is_zero(): void
    {
        $result = $this->calculator->calculate(
            directionAccuracy: 0.45,
            strategyReturn: 0.0,
            rmse: 0.05,
            stability: 0.0,
            predictionCount: 0,
        );

        $this->assertSame(
            0.0,
            $result->normalizedMetrics['direction_accuracy']
        );
    }

    public function test_direction_accuracy_at_seventy_percent_is_full_score(): void
    {
        $result = $this->calculator->calculate(
            directionAccuracy: 0.70,
            strategyReturn: 0.0,
            rmse: 0.05,
            stability: 0.0,
            predictionCount: 0,
        );

        $this->assertSame(
            1.0,
            $result->normalizedMetrics['direction_accuracy']
        );
    }

    public function test_rmse_is_scored_inversely(): void
    {
        $good = $this->calculator->calculate(
            directionAccuracy: 0.50,
            strategyReturn: 0.0,
            rmse: 0.01,
            stability: 0.0,
            predictionCount: 0,
        );

        $bad = $this->calculator->calculate(
            directionAccuracy: 0.50,
            strategyReturn: 0.0,
            rmse: 0.05,
            stability: 0.0,
            predictionCount: 0,
        );

        $this->assertGreaterThan(
            $bad->normalizedMetrics['rmse'],
            $good->normalizedMetrics['rmse'],
        );
    }

    public function test_prediction_count_reaches_full_score_at_one_thousand(): void
    {
        $result = $this->calculator->calculate(
            directionAccuracy: 0.50,
            strategyReturn: 0.0,
            rmse: 0.05,
            stability: 0.0,
            predictionCount: 1000,
        );

        $this->assertSame(
            1.0,
            $result->normalizedMetrics['prediction_count']
        );
    }

    public function test_custom_weights_are_normalized(): void
    {
        $result = $this->calculator->calculate(
            directionAccuracy: 0.60,
            strategyReturn: 0.05,
            rmse: 0.03,
            stability: 0.50,
            predictionCount: 500,
            weights: [
                'direction_accuracy' => 35,
                'strategy_return' => 30,
                'rmse' => 15,
                'stability' => 15,
                'prediction_count' => 5,
            ],
        );

        $this->assertEqualsWithDelta(
            1.0,
            array_sum($result->weights),
            0.000001,
        );
    }

    public function test_better_metrics_produce_a_higher_score(): void
    {
        $weaker = $this->calculator->calculate(
            directionAccuracy: 0.56,
            strategyReturn: 0.02,
            rmse: 0.04,
            stability: 0.60,
            predictionCount: 200,
        );

        $stronger = $this->calculator->calculate(
            directionAccuracy: 0.65,
            strategyReturn: 0.08,
            rmse: 0.02,
            stability: 0.92,
            predictionCount: 1200,
        );

        $this->assertGreaterThan(
            $weaker->score,
            $stronger->score,
        );
    }

    public function test_missing_weight_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(
            directionAccuracy: 0.60,
            strategyReturn: 0.05,
            rmse: 0.03,
            stability: 0.80,
            predictionCount: 500,
            weights: [
                'direction_accuracy' => 0.50,
            ],
        );
    }

    public function test_negative_prediction_count_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(
            directionAccuracy: 0.60,
            strategyReturn: 0.05,
            rmse: 0.03,
            stability: 0.80,
            predictionCount: -1,
        );
    }

    public function test_result_is_serializable(): void
    {
        $result = $this->calculator->calculate(
            directionAccuracy: 0.60,
            strategyReturn: 0.05,
            rmse: 0.03,
            stability: 0.80,
            predictionCount: 500,
        )->toArray();

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('normalized_metrics', $result);
        $this->assertArrayHasKey('weighted_metrics', $result);
        $this->assertArrayHasKey('weights', $result);
    }
}
