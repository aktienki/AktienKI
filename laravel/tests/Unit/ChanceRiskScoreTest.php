<?php

namespace Tests\Unit;

use App\Support\ChanceRiskScore;
use PHPUnit\Framework\TestCase;

final class ChanceRiskScoreTest extends TestCase
{
    public function test_strong_confirmed_positive_forecast_has_more_chance_and_less_risk(): void
    {
        $positive = ChanceRiskScore::calculate(85, [5 => 3, 10 => 5, 15 => 7, 20 => 10], 80, 25);
        $negative = ChanceRiskScore::calculate(85, [5 => -3, 10 => -5, 15 => -7, 20 => -10], 80, 25);

        self::assertGreaterThan($negative['chance'], $positive['chance']);
        self::assertLessThan($negative['risk'], $positive['risk']);
        self::assertGreaterThanOrEqual(70, $positive['chance']);
        self::assertGreaterThanOrEqual(50, $negative['risk']);
    }

    public function test_quality_strengthens_direction_instead_of_becoming_direction(): void
    {
        $highQualityNegative = ChanceRiskScore::calculate(90, [5 => -2, 10 => -4, 15 => -6, 20 => -9], 80, 30);
        $lowQualityNegative = ChanceRiskScore::calculate(30, [5 => -2, 10 => -4, 15 => -6, 20 => -9], 80, 30);

        self::assertLessThan($lowQualityNegative['chance'], $highQualityNegative['chance']);
        self::assertGreaterThan($lowQualityNegative['risk'], $highQualityNegative['risk']);
    }

    public function test_missing_horizons_reduce_completeness_and_add_risk(): void
    {
        $complete = ChanceRiskScore::calculate(80, [5 => 6, 10 => 6, 15 => 6, 20 => 6], 75, 30);
        $partial = ChanceRiskScore::calculate(80, [20 => 6], 75, 30);

        self::assertSame(100.0, $complete['completeness']);
        self::assertSame(45.0, $partial['completeness']);
        self::assertGreaterThan($complete['risk'], $partial['risk']);
    }

    public function test_grades_are_simple_and_risk_is_inverted(): void
    {
        self::assertSame(1, ChanceRiskScore::grade(84));
        self::assertSame(5, ChanceRiskScore::grade(18));
        self::assertSame(1, ChanceRiskScore::grade(18, true));
        self::assertSame(5, ChanceRiskScore::grade(84, true));
        self::assertSame(2, ChanceRiskScore::equityRiskGrade(5));
        self::assertSame(5, ChanceRiskScore::equityRiskGrade(84));
        self::assertSame('1+', ChanceRiskScore::chanceLabel(97));
        self::assertSame('1−', ChanceRiskScore::chanceLabel(82));
        self::assertSame('2+', ChanceRiskScore::equityRiskLabel(5));
        self::assertSame('2−', ChanceRiskScore::equityRiskLabel(32));
        self::assertSame('5−', ChanceRiskScore::equityRiskLabel(95));
    }
}
