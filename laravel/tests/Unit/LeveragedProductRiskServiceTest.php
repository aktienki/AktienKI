<?php

namespace Tests\Unit;

use App\Services\LeveragedProductRiskService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LeveragedProductRiskServiceTest extends TestCase
{
    #[DataProvider('percentileCases')]
    public function test_it_calculates_a_point_in_time_percentile(float $value, array $history, float $expected): void
    {
        $service = new LeveragedProductRiskService;

        self::assertEqualsWithDelta($expected, $service->percentileRank($value, $history), 0.0001);
    }

    public static function percentileCases(): array
    {
        return [
            'empty history is neutral' => [4.0, [], 50.0],
            'below all history' => [0.0, [1.0, 2.0, 3.0], 0.0],
            'above all history' => [4.0, [1.0, 2.0, 3.0], 100.0],
            'ties use midpoint rank' => [2.0, [1.0, 2.0, 2.0, 3.0], 50.0],
        ];
    }

    public function test_long_and_short_returns_use_the_same_entry_notional(): void
    {
        $service = new LeveragedProductRiskService;

        self::assertEqualsWithDelta(0.10, $service->positionReturn('long', 100, 110), 0.0001);
        self::assertEqualsWithDelta(-0.10, $service->positionReturn('short', 100, 110), 0.0001);
        self::assertEqualsWithDelta(0.10, $service->positionReturn('short', 100, 90), 0.0001);
    }

    public function test_historical_monte_carlo_is_deterministic_and_reports_barrier_risk(): void
    {
        $service = new LeveragedProductRiskService;
        $prices = [];
        $price = 100.0;
        for ($day = 0; $day < 100; $day++) {
            $price *= 1 + (($day % 5) - 2) / 100;
            $prices[] = $price;
        }

        $first = $service->simulateHistoricalPaths($prices, 20, 500, 42, 5.0);
        $second = $service->simulateHistoricalPaths($prices, 20, 500, 42, 5.0);

        self::assertSame($first, $second);
        self::assertSame(500, $first['simulations']);
        self::assertCount(20, $first['quantile_path']);
        self::assertSame(1, $first['quantile_path'][0]['day']);
        self::assertSame(20, $first['quantile_path'][19]['day']);
        self::assertGreaterThanOrEqual(0, $first['sides']['long']['forecast_target_probability_percent']);
        self::assertLessThanOrEqual(100, $first['sides']['long']['forecast_target_probability_percent']);
        self::assertSame(range(10, 100, 10), $first['loss_thresholds_percent']);
        self::assertCount(5, $first['loss_probability_matrix']['long']);
        self::assertArrayHasKey('100', $first['loss_probability_matrix']['short'][4]['probabilities']);
        self::assertCount(4, $first['sides']['long']['cells']);
        self::assertEqualsWithDelta(20.0, $first['sides']['long']['cells'][0]['indicative_leverage'], 0.0001);
        self::assertGreaterThanOrEqual(0, $first['sides']['short']['cells'][0]['barrier_breach_probability_percent']);
        self::assertLessThanOrEqual(100, $first['sides']['short']['cells'][0]['barrier_breach_probability_percent']);
    }
}
