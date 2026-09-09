<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StockPanelSectorDonutTest extends TestCase
{
    public function test_stock_details_compare_panel_score_with_sector_peers(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/StockController.php');

        $this->assertStringContainsString('$this->panelSectorSnapshot($instrument)', $controller);
        $this->assertStringContainsString("->where('peer.sector', (string) \$instrument->sector)", $controller);
        $this->assertStringContainsString("->pluck('panel.raw_score')", $controller);
        $this->assertStringContainsString("'panelSector',", $controller);
    }

    public function test_stock_details_render_panel_sector_as_third_donut(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/stocks/show.blade.php');

        $this->assertStringContainsString('$panelSectorPercent', $view);
        $this->assertStringContainsString(":display=\"\$panelSectorDecile !== null ? 'D'.\$panelSectorDecile : '—'\"", $view);
        $this->assertSame(2, substr_count($view, "__('Panel Sektor')"));
        $this->assertStringContainsString('grid-template-columns: minmax(210px, 235px)', $view);
    }

    public function test_historical_panel_score_uses_its_own_indicator_chart(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/stocks/show.blade.php');

        $this->assertStringContainsString('id="stock-panel-history-panel"', $view);
        $this->assertStringContainsString('id="stock-detail-panel-history"', $view);
        $this->assertStringContainsString('const panelHistoryOptions = () =>', $view);
        $this->assertStringContainsString("series: [{ name: 'Panel', data: panelHistoryData() }]", $view);
        $this->assertStringContainsString("formatter: value => `D\${Math.round(value)}`", $view);
        $this->assertStringNotContainsString("panelAxisTitle.textContent = 'PANEL'", $view);
        $this->assertStringNotContainsString('const visiblePanelScores = historicalPanelScores', $view);
    }
}
