<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ScreenerMobilePerformanceTest extends TestCase
{
    public function test_live_simulation_is_disabled_on_mobile_and_row_updates_are_batched(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/screener/index.blade.php');

        self::assertStringContainsString("window.matchMedia('(max-width:767px)').matches", $view);
        self::assertStringContainsString('const affectedRows=new Set()', $view);
        self::assertStringContainsString('affectedRows.forEach(updateForecastWarnings)', $view);
    }
}
