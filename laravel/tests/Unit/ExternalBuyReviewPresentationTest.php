<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ExternalBuyReviewPresentationTest extends TestCase
{
    public function test_external_buy_reviews_start_with_a_clear_decision_and_adjustment(): void
    {
        $root = dirname(__DIR__, 2);
        $screener = (string) file_get_contents($root.'/resources/views/screener/index.blade.php');
        $stock = (string) file_get_contents($root.'/resources/views/stocks/show.blade.php');
        $mail = (string) file_get_contents($root.'/resources/views/mail/partials/external-buy-review.blade.php');

        foreach ([$screener, $stock, $mail] as $view) {
            $this->assertStringContainsString("'NO_OBJECTION' => __('BUY bestätigt')", $view);
            $this->assertStringContainsString("'CAUTION', 'OBJECTION' => __('BUY extern abgestuft')", $view);
            $this->assertStringContainsString("? __('JA') : __('NEIN')", $view);
        }

        $this->assertStringContainsString("__('BUY auf :signal abgestuft'", $screener);
        $this->assertStringContainsString('$internalAssessmentDecision', $screener);
    }
}
