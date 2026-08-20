<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StockRiskClassificationService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class StockRiskClassificationServiceTest extends TestCase
{
    #[DataProvider('classifications')]
    public function test_it_classifies_validated_metrics(?float $pf, ?float $confidence, ?float $drawdown, ?string $expected): void
    {
        $this->assertSame($expected, app(StockRiskClassificationService::class)->classify($pf, $confidence, $drawdown));
    }

    public static function classifications(): array
    {
        return [
            'missing stays unclassified' => [null, 80, 10, null],
            'below corrected threshold sleeps' => [1.0499, 99, 1, 'sleep'],
            'threshold can recover' => [1.05, 50, 30, 'opportunity'],
            'defensive' => [1.35, 70, 18, 'defensive'],
            'balanced' => [1.20, 58, 28, 'balanced'],
            'opportunity' => [1.10, 45, 40, 'opportunity'],
            'positive but exceptionally risky' => [1.10, 30, 60, 'risk'],
        ];
    }

    public function test_risk_profile_is_the_only_profile_that_sees_sleep(): void
    {
        $service = app(StockRiskClassificationService::class);
        $riskUser = new User(['meta' => ['risk_profile' => ['level' => 'risk']]]);
        $balancedUser = new User(['meta' => ['risk_profile' => ['level' => 'normal']]]);

        $this->assertContains('sleep', $service->visibleStatuses($riskUser));
        $this->assertNotContains('sleep', $service->visibleStatuses($balancedUser));
    }
}
