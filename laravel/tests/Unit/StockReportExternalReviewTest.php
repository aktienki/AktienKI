<?php

namespace Tests\Unit;

use App\Http\Controllers\StockController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class StockReportExternalReviewTest extends TestCase
{
    public function test_serving_stock_report_contains_the_external_assessment_and_comment(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/StockController.php');
        $report = (string) file_get_contents($root.'/resources/views/stocks/serving-report.blade.php');

        $this->assertStringContainsString("\$externalBuyReview = \$detail['externalBuyReview']", $controller);
        $this->assertStringContainsString("'externalBuyReview', 'externalBuyReviewPositiveFactors', 'externalBuyReviewRiskFactors'", $controller);
        $this->assertStringContainsString("\$t('Externe KI-Bewertung','External AI review')", $report);
        $this->assertStringContainsString("\$t('Externer Kommentar','External comment')", $report);
        $this->assertStringContainsString('$externalBuyReview->summary', $report);
        $this->assertStringContainsString("'CAUTION','OBJECTION'=>'negative'", $report);
        $this->assertStringContainsString("'INSUFFICIENT_EVIDENCE','failed'=>'amber'", $report);
    }

    public function test_serving_report_chart_contains_candles_predictions_probabilities_and_panel_axis(): void
    {
        $controller = (new ReflectionClass(StockController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(StockController::class, 'servingReportChart');
        $dataUrl = $method->invoke($controller, [
            ['timestamp' => 1_787_529_600, 'open' => 100, 'high' => 104, 'low' => 98, 'close' => 102],
            ['timestamp' => 1_787_616_000, 'open' => 102, 'high' => 103, 'low' => 99, 'close' => 100],
            ['timestamp' => 1_787_702_400, 'open' => 100, 'high' => 106, 'low' => 99, 'close' => 105],
        ], [
            10 => ['price' => 108, 'confidence' => 67],
            20 => ['price' => 110, 'confidence' => 59],
            40 => ['price' => 114, 'confidence' => 67],
        ], [
            ['x' => 1_787_529_600_000, 'y' => 6.2],
            ['x' => 1_787_702_400_000, 'y' => 6.7],
        ]);
        $svg = base64_decode(substr($dataUrl, strlen('data:image/svg+xml;base64,')));

        $this->assertStringContainsString('<clipPath id="plot">', $svg);
        $this->assertStringContainsString('fill="#22c55e"', $svg);
        $this->assertStringContainsString('fill="#ef4444"', $svg);
        $this->assertStringContainsString('Wahrscheinlichkeit 67 %', $svg);
        $this->assertStringContainsString('>40T</text>', $svg);
        $this->assertStringContainsString('>PANEL</text>', $svg);
        $this->assertStringContainsString('D6,7', $svg);
    }
}
