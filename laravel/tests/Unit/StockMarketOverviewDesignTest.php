<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StockMarketOverviewDesignTest extends TestCase
{
    public function test_market_and_stock_pages_share_the_market_overview_skin(): void
    {
        $root = dirname(__DIR__, 2);
        $theme = (string) file_get_contents($root.'/resources/views/components/global-light-theme.blade.php');
        $market = (string) file_get_contents($root.'/resources/views/markets/situation.blade.php');
        $stock = (string) file_get_contents($root.'/resources/views/stocks/show.blade.php');

        $this->assertStringContainsString('--ak-overview-card:', $theme);
        $this->assertStringContainsString('--ak-overview-header:', $theme);
        $this->assertStringContainsString('--ak-overview-shadow:', $theme);
        $this->assertStringContainsString('ak-market-situation-page ak-market-overview-skin', $market);
        $this->assertStringContainsString('id="stock-detail-page"', $stock);
        $this->assertStringContainsString('class="ak-market-overview-skin ', $stock);
        $this->assertStringContainsString('#stock-detail-page.ak-market-overview-skin .stock-detail-header', $stock);
        $this->assertStringContainsString('#stock-detail-page.ak-market-overview-skin .stock-detail-panel', $stock);
        $this->assertStringContainsString('background: var(--ak-overview-header) !important;', $stock);
    }
}
