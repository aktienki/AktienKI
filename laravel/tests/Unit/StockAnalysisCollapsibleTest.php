<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StockAnalysisCollapsibleTest extends TestCase
{
    public function test_personalized_analysis_is_always_open(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/stocks/show.blade.php');

        $this->assertStringNotContainsString('x-data="{ analysisOpen: false }"', $view);
        $this->assertStringNotContainsString('aria-controls="stock-analysis-content"', $view);
        $this->assertStringContainsString('id="stock-analysis-content"', $view);
        $this->assertStringNotContainsString('x-show="analysisOpen"', $view);
        $this->assertStringContainsString('class="hidden h-8 items-center justify-center', $view);
        $this->assertStringContainsString('w-full items-center justify-center gap-1.5', $view);
        $this->assertStringContainsString('truncate whitespace-nowrap text-[9px]', $view);
    }

    public function test_serving_forecast_email_buttons_open_the_reminder_modal_for_pro(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/stocks/show.blade.php');

        $this->assertStringContainsString('$canCreatePredictionReminder = $canViewRealtime;', $view);
        $this->assertStringContainsString('@click="openReminder({{ $horizonDays }})"', $view);
        $this->assertStringContainsString('x-show="reminderOpen"', $view);
        $this->assertStringContainsString("route('stocks.purchase-reminder.store', \$instrument->id)", $view);
    }
}
