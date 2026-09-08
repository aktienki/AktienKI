<?php

namespace Tests\Unit;

use Illuminate\Mail\Markdown;
use Tests\TestCase;

final class TrainingCompletedEmailModelComparisonTest extends TestCase
{
    public function test_email_shows_standard_tcn_and_active_model_for_each_horizon(): void
    {
        $html = (string) app(Markdown::class)->render('mail.training-completed', [
            'name' => 'Test AG',
            'symbol' => 'TST',
            'sourceLabel' => 'Mac Mini',
            'finishedAt' => 'jetzt',
            'holdingPolicy' => 'Festhorizont',
            'horizons' => [[
                'days' => 20,
                'activeVariantLabel' => 'Reines TCN',
                'activeModel' => 'Pure TCN',
                'standard' => [
                    'trades' => 12, 'hitRate' => '58,3 %',
                    'profitFactor' => '1,40', 'averageReturn' => '+1,20 %',
                ],
                'pureTcn' => [
                    'trades' => 10, 'hitRate' => '70,0 %',
                    'profitFactor' => '2,10', 'averageReturn' => '+2,00 %',
                ],
            ]],
            'filteredPerformance' => ['available' => false],
            'validationPassed' => false,
            'qualityClass' => 'Solid',
            'statusLabel' => 'Dokumentiert',
            'minimumAiScore' => '0,2',
            'duration' => '–',
        ]);

        $this->assertStringContainsString('Standard und reines TCN', $html);
        $this->assertStringContainsString('AKTIV: Reines TCN · Pure TCN', $html);
        $this->assertStringContainsString('70,0 %', $html);
        $this->assertStringContainsString('2,10', $html);
    }
}
