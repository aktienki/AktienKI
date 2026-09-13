<?php

namespace Tests\Unit;

use App\Services\ServingScreenerService;
use ReflectionClass;
use Tests\TestCase;

final class ServingScreenerRankingTest extends TestCase
{
    public function test_composite_score_dominates_the_screener_ranking(): void
    {
        $service = (new ReflectionClass(ServingScreenerService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(ServingScreenerService::class))->getMethod('rankingPriority');

        $higherCompositeScore = (object) [
            'composite_score' => 81,
            'ranking_score' => 1,
            'expected_return_20d' => -10,
            'risk_percent' => 100,
        ];
        $lowerCompositeScore = (object) [
            'composite_score' => 80,
            'ranking_score' => 100,
            'expected_return_20d' => 100,
            'risk_percent' => 0,
        ];

        $this->assertGreaterThan(
            $method->invoke($service, $lowerCompositeScore),
            $method->invoke($service, $higherCompositeScore),
        );
    }

    public function test_legacy_model_score_only_breaks_equal_composite_scores(): void
    {
        $service = (new ReflectionClass(ServingScreenerService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(ServingScreenerService::class))->getMethod('rankingPriority');

        $higherModelScore = (object) [
            'composite_score' => 80,
            'ranking_score' => 75,
            'expected_return_20d' => 2,
            'risk_percent' => 30,
        ];
        $lowerModelScore = (object) [
            'composite_score' => 80,
            'ranking_score' => 70,
            'expected_return_20d' => 2,
            'risk_percent' => 30,
        ];

        $this->assertGreaterThan(
            $method->invoke($service, $lowerModelScore),
            $method->invoke($service, $higherModelScore),
        );
    }
}
