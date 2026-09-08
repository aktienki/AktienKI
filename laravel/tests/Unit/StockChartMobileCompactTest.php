<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StockChartMobileCompactTest extends TestCase
{
    public function test_mobile_chart_header_is_compact_without_removing_indicators(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/stocks/show.blade.php');

        $this->assertStringContainsString('#stock-detail-page .stock-chart-toolbar {', $view);
        $this->assertStringContainsString('display: flex !important;', $view);
        $this->assertStringContainsString("<span class=\"sm:hidden\">{{ __('KI-Score') }}</span>", $view);
        $this->assertStringContainsString("<span class=\"sm:hidden\">{{ __('Seit Signal') }}</span>", $view);
        $this->assertStringContainsString('class="stock-mobile-indicators group', $view);
        $this->assertStringContainsString('id="stock-indicator-buttons"', $view);
    }

    public function test_indicator_analysis_remains_a_standalone_card(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/stocks/show.blade.php');

        $this->assertStringContainsString(
            'id="indicator-statistics" data-stock-collapsible="indicators"',
            $view,
        );
        $this->assertStringNotContainsString('stock-indicator-statistics-slot', $view);
        $this->assertStringNotContainsString('appendChild(standaloneIndicatorSection)', $view);
    }
}
