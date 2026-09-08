<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FinalEntryConsumerContractTest extends TestCase
{
    public function test_visible_serving_buy_surfaces_use_the_final_entry_reader(): void
    {
        $root = dirname(__DIR__, 2);
        $screener = (string) file_get_contents($root.'/app/Services/ServingScreenerService.php');
        $predictionTable = (string) file_get_contents($root.'/app/Http/Controllers/ServingPredictionTableController.php');
        $dashboard = (string) file_get_contents($root.'/app/Http/Controllers/DashboardController.php');
        $stockDetail = (string) file_get_contents($root.'/app/Services/ServingStockLegacyViewService.php');

        $this->assertStringContainsString('FinalEntrySignalReadService $finalEntries', $screener);
        $this->assertStringContainsString('$this->applyFinalEntrySignals($stocks', $screener);
        $this->assertStringContainsString('userProfileBySourceInstrument(', $screener);
        $this->assertStringContainsString('currentSignal(', $screener);
        $this->assertStringContainsString('userProfileBySourcePrediction(', $predictionTable);
        $this->assertStringContainsString('gateSignal(', $predictionTable);
        $this->assertStringContainsString('$this->signalCockpit($user)', $dashboard);
        $this->assertStringContainsString('userProfileBySourceInstrument(', $dashboard);
        $this->assertStringContainsString('gateSignal(', $dashboard);
        $this->assertStringContainsString('projectFinalSignalCounts(', $dashboard);
        $this->assertStringContainsString('userProfileBySourceInstrument(', $stockDetail);
        $this->assertStringContainsString('currentSignal(', $stockDetail);
    }

    public function test_buy_email_is_checked_at_scan_and_again_immediately_before_send(): void
    {
        $root = dirname(__DIR__, 2);
        $scanner = (string) file_get_contents($root.'/app/Services/SignalEmailService.php');
        $job = (string) file_get_contents($root.'/app/Jobs/SendSignalEmailAfterReview.php');

        $this->assertStringContainsString('finalEntryAllowsSavedFilter(', $scanner);
        $this->assertStringContainsString('finalEntryAllowsUserProfile(', $scanner);
        $this->assertStringContainsString('if (! $this->finalEntryStillAllowsBuy())', $job);
        $this->assertStringContainsString("'status' => 'suppressed'", $job);
        $this->assertStringContainsString('allowsSavedFilterBuy(', $job);

        $entryAlert = (string) file_get_contents($root.'/app/Console/Commands/SendEntrySignalAlerts.php');
        $this->assertStringContainsString("\$currentSignal === 'BUY'", $entryAlert);
        $this->assertStringContainsString('allowsUserProfileBuyForLocalInstrument(', $entryAlert);
    }

    public function test_automation_preserves_exit_processing_and_claims_one_final_entry_before_purchase(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Services/AutomatedPortfolioService.php',
        );

        $exitAt = strpos($source, '$this->processDynamicExits($strategy, $portfolio);');
        $candidateAt = strpos($source, '$candidates = $this->candidates($strategy);');
        $claimAt = strpos($source, 'if (! $this->claimFinalEntry($strategy, $portfolio, $candidate))');
        $positionAt = strpos($source, '$position = PortfolioPosition::query()->make(');

        $this->assertIsInt($exitAt);
        $this->assertIsInt($candidateAt);
        $this->assertIsInt($claimAt);
        $this->assertIsInt($positionAt);
        $this->assertLessThan($candidateAt, $exitAt);
        $this->assertLessThan($positionAt, $claimAt);
        $this->assertStringContainsString('savedFilterByInstrument(', $source);
        $this->assertStringContainsString("->whereNull('entry_consumed_at')", $source);
        $this->assertStringContainsString("'entry_consumer_type' => 'AUTOMATION'", $source);
        $this->assertStringContainsString("DB::table('current_final_entry_signals')", $source);
    }
}
