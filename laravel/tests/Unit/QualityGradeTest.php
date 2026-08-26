<?php

namespace Tests\Unit;

use App\Support\QualityGrade;
use PHPUnit\Framework\TestCase;

final class QualityGradeTest extends TestCase
{
    public function test_quality_scale_has_ten_ordered_classes(): void
    {
        $this->assertSame('1+', QualityGrade::fromPercent(100));
        $this->assertSame('1+', QualityGrade::fromPercent(90));
        $this->assertSame('1−', QualityGrade::fromPercent(89.9));
        $this->assertSame('3+', QualityGrade::fromPercent(50));
        $this->assertSame('5−', QualityGrade::fromPercent(0));
    }

    public function test_risk_is_inverted_so_low_risk_is_good(): void
    {
        $this->assertSame('1+', QualityGrade::risk(0));
        $this->assertSame('5−', QualityGrade::risk(100));
        $this->assertNull(QualityGrade::risk(null));
    }

    public function test_risk_level_runs_from_one_low_to_five_high(): void
    {
        $this->assertSame(2, QualityGrade::riskLevel(0));
        $this->assertSame(2, QualityGrade::riskLevel(20));
        $this->assertSame(3, QualityGrade::riskLevel(50));
        $this->assertSame(5, QualityGrade::riskLevel(100));
    }
}
