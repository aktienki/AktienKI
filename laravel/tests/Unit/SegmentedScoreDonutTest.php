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
        $this->assertStringContainsString('? ($segmentCount + 1) - $gradeLevel', $component);
        $this->assertStringContainsString(": ['#df4d5f', '#ed8a32', '#e1be32', '#8fca45', '#35b779']", $component);
        $this->assertStringContainsString('$sectorOffset = $segmentCount === 10 ? 10.0 : 18.5', $component);
    }

    public function test_donut_supports_a_ten_sector_red_to_green_scale(): void
    {
        $component = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/components/segmented-score-donut.blade.php',
        );

        $this->assertStringContainsString("'segments' => 5", $component);
        $this->assertStringContainsString("['#df4d5f', '#e45f4d', '#e8753a', '#ed8a32', '#eaa632', '#e1be32', '#bed23b', '#8fca45', '#5fc060', '#35b779']", $component);
        $this->assertStringContainsString('$sectorLength = $segmentCount === 10 ? 8.0 : 15.5', $component);
    }
}
