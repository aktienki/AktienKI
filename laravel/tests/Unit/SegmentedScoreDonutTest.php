<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SegmentedScoreDonutTest extends TestCase
{
    public function test_risk_scale_runs_clockwise_from_five_to_one(): void
    {
        $component = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/components/segmented-score-donut.blade.php',
        );

        $this->assertStringContainsString('$activeLevel = $type === \'risk\' && $gradeLevel > 0', $component);
        $this->assertStringContainsString('? 6 - $gradeLevel', $component);
        $this->assertStringContainsString("? ['#df4d5f', '#ed8a32', '#e1be32', '#8fca45', '#35b779']", $component);
        $this->assertStringContainsString('stroke-dashoffset="{{ -($index * 18.5) }}"', $component);
    }
}
