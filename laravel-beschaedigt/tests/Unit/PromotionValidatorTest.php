<?php

namespace Tests\Unit;

use App\Services\Champion\PromotionValidator;
use App\Services\Elo\EloCalculator;
use App\Services\Elo\EloRatingService;
use App\Services\ModelComparison\ModelComparisonService;
use App\Services\ModelComparison\ModelMetrics;
use App\Services\ModelComparison\SelectionScoreCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PromotionValidatorTest extends TestCase
{
    private PromotionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new PromotionValidator();
    }

    public function test_it_approves_a_valid_promotion(): void
    {
        $decision = $this->validator->validate(
            championModelId: 10,
            challengerModelId: 11,
            champion: $this->championMetrics(),
            challenger: $this->challengerMetrics(),
            comparison: $this->comparisonResult(),
            championActivatedAt: new DateTimeImmutable(
                '2026-05-01T00:00:00+00:00'
            ),
            decidedAt: new DateTimeImmutable(
                '2026-07-12T00:00:00+00:00'
            ),
        );

        $this->assertTrue($decision->promote);
        $this->assertTrue(
            $decision->checks['minimum_predictions']
        );
        $this->assertTrue(
            $decision->checks['cooldown_complete']
        );
    }

    public function test_it_rejects_when_cooldown_is_active(): void
    {
        $decision = $this->validator->validate(
            championModelId: 10,
            challengerModelId: 11,
            champion: $this->championMetrics(),
            challenger: $this->challengerMetrics(),
            comparison: $this->comparisonResult(),
            championActivatedAt: new DateTimeImmutable(
                '2026-07-01T00:00:00+00:00'
            ),
            decidedAt: new DateTimeImmutable(
                '2026-07-12T00:00:00+00:00'
            ),
        );

        $this->assertFalse($decision->promote);
        $this->assertFalse(
            $decision->checks['cooldown_complete']
        );
        $this->assertNotNull($decision->cooldownUntil);
    }

    public function test_it_rejects_too_few_predictions(): void
    {
        $challenger = new ModelMetrics(
            directionAccuracy: 0.65,
            strategyReturn: 0.08,
            rmse: 0.02,
            stability: 0.92,
            predictionCount: 200,
            eloRating: 1500,
        );

        $decision = $this->validator->validate(
            championModelId: 10,
            challengerModelId: 11,
            champion: $this->championMetrics(),
            challenger: $challenger,
            comparison: $this->comparisonResult(
                challenger: $challenger
            ),
            championActivatedAt: new DateTimeImmutable(
                '2026-05-01T00:00:00+00:00'
            ),
            decidedAt: new DateTimeImmutable(
                '2026-07-12T00:00:00+00:00'
            ),
        );

        $this->assertFalse($decision->promote);
        $this->assertFalse(
            $decision->checks['minimum_predictions']
        );
    }

    public function test_it_rejects_worse_rmse(): void
    {
        $challenger = new ModelMetrics(
            directionAccuracy: 0.66,
            strategyReturn: 0.09,
            rmse: 0.04,
            stability: 0.92,
            predictionCount: 1200,
            eloRating: 1500,
        );

        $decision = $this->validator->validate(
            championModelId: 10,
            challengerModelId: 11,
            champion: $this->championMetrics(),
            challenger: $challenger,
            comparison: $this->comparisonResult(
                challenger: $challenger
            ),
            championActivatedAt: new DateTimeImmutable(
                '2026-05-01T00:00:00+00:00'
            ),
            decidedAt: new DateTimeImmutable(
                '2026-07-12T00:00:00+00:00'
            ),
        );

        $this->assertFalse($decision->promote);
        $this->assertFalse(
            $decision->checks['rmse_not_worse']
        );
    }

    public function test_custom_rules_can_relax_cooldown(): void
    {
        $decision = $this->validator->validate(
            championModelId: 10,
            challengerModelId: 11,
            champion: $this->championMetrics(),
            challenger: $this->challengerMetrics(),
            comparison: $this->comparisonResult(),
            championActivatedAt: new DateTimeImmutable(
                '2026-07-01T00:00:00+00:00'
            ),
            decidedAt: new DateTimeImmutable(
                '2026-07-12T00:00:00+00:00'
            ),
            rules: [
                'cooldown_days' => 7,
            ],
        );

        $this->assertTrue($decision->promote);
    }

    private function comparisonResult(
        ?ModelMetrics $challenger = null,
    ) {
        $challenger ??= $this->challengerMetrics();

        return (
            new ModelComparisonService(
                new SelectionScoreCalculator(),
                new EloRatingService(
                    new EloCalculator()
                ),
            )
        )->compare(
            champion: $this->championMetrics(),
            challenger: $challenger,
            rules: [
                'minimum_validated_predictions' => 1000,
                'minimum_selection_score_difference' => 0.02,
                'minimum_direction_accuracy_difference' => 0.01,
                'minimum_strategy_return_difference' => 0.005,
                'elo_k_factor' => 32,
            ],
        );
    }

    private function championMetrics(): ModelMetrics
    {
        return new ModelMetrics(
            directionAccuracy: 0.58,
            strategyReturn: 0.03,
            rmse: 0.03,
            stability: 0.75,
            predictionCount: 1200,
            eloRating: 1550,
        );
    }

    private function challengerMetrics(): ModelMetrics
    {
        return new ModelMetrics(
            directionAccuracy: 0.65,
            strategyReturn: 0.08,
            rmse: 0.02,
            stability: 0.92,
            predictionCount: 1200,
            eloRating: 1500,
        );
    }
}
