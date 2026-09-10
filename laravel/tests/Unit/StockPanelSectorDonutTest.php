<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StockPanelSectorDonutTest extends TestCase
{
    public function test_stock_details_compare_panel_score_with_sector_peers(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/StockController.php');
        $servingAdapter = (string) file_get_contents($root.'/app/Services/ServingStockLegacyViewService.php');

        $this->assertStringContainsString('$this->panelSectorSnapshot($instrument)', $controller);
        $this->assertStringContainsString("->where('peer.sector', (string) \$instrument->sector)", $controller);
        $this->assertStringContainsString("->pluck('panel.raw_score')", $controller);
        $this->assertStringContainsString("'panelSector',", $controller);
        $this->assertStringContainsString('$panelInstrument = $this->panelInstrument($instrument)', $servingAdapter);
        $this->assertStringContainsString("->orWhereRaw('UPPER(provider_symbol) = ?'", $servingAdapter);
        $this->assertStringContainsString("'panelSector' => \$panelSector", $servingAdapter);
        $this->assertStringContainsString('panel_universe.as_of_date = stock_panel.as_of_date', $servingAdapter);
    }

    public function test_stock_details_render_panel_sector_as_third_donut(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/stocks/show.blade.php');

        $this->assertStringContainsString('$panelSectorPercent', $view);
        $this->assertStringContainsString(":display=\"\$panelSectorDecile !== null ? 'D'.\$panelSectorDecile : '—'\"", $view);
        $this->assertSame(2, substr_count($view, "__('Panel Sektor')"));
        $this->assertStringContainsString('grid-template-columns: minmax(210px, 235px)', $view);
    }

    public function test_panel_score_and_prediction_are_integrated_into_the_main_chart(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/stocks/show.blade.php');

        $this->assertStringNotContainsString('id="stock-panel-history-panel"', $view);
        $this->assertStringNotContainsString('id="stock-detail-panel-history"', $view);
        $this->assertStringContainsString('const visiblePanelScores = panelPoints.filter', $view);
        $this->assertStringContainsString("label.textContent = `PANEL D\${score}`", $view);
        $this->assertStringContainsString('const forecastEnd = addTradingDays(latestPanel.x, 20)', $view);
        $this->assertStringContainsString('PANEL 20T · D', $view);
        $this->assertStringContainsString('latestPanel.predictionPercent', $view);

        $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/StockController.php');
        $this->assertStringContainsString("->get(['as_of_date', 'raw_score', 'xsec_pctile', 'decile'])", $controller);
        $this->assertStringContainsString("'prediction_percent' => is_numeric(\$row->raw_score)", $controller);
    }
}
