<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ScreenerMobilePerformanceTest extends TestCase
{
    public function test_mobile_live_simulation_is_bounded_and_row_updates_are_batched(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/screener/index.blade.php');

        self::assertStringContainsString("window.matchMedia('(max-width:767px)').matches", $view);
        self::assertStringContainsString('.values()].slice(0,20)', $view);
        self::assertStringContainsString('isMobile?10000:5000', $view);
        self::assertStringContainsString('const amplitude=isMobile?.015:.004', $view);
        self::assertStringContainsString('const affectedRows=new Set()', $view);
        self::assertStringContainsString('affectedRows.forEach(updateForecastWarnings)', $view);
        self::assertStringContainsString('data-live-base-price="{{ (float) $stock->current_price }}"', $view);
        self::assertStringContainsString('screener-dynamic-marker-pulse', $view);
    }
}
