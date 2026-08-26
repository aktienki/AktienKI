<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StockRiskClassificationService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class StockRiskClassificationServiceTest extends TestCase
{
    #[DataProvider('classifications')]
    public function test_it_classifies_validated_metrics(?float $pf, ?float $profitPerTrade, ?float $confidence, ?float $drawdown, ?string $expected): void
    {
        $this->assertSame($expected, app(StockRiskClassificationService::class)->classify($pf, $profitPerTrade, $confidence, $drawdown));
    }

    public static function classifications(): array
    {
        return [
            'missing stays unclassified' => [null, 1, 80, 10, null],
            'negative average trade sleeps' => [2.0, -0.01, 99, 1, 'sleep'],
            'zero average trade does not sleep' => [1.05, 0, 50, 30, 'opportunity'],
            'low profit factor alone no longer sleeps' => [0.90, 1, 99, 1, 'risk'],
            'defensive' => [1.35, 1, 70, 18, 'defensive'],
            'balanced' => [1.20, 1, 58, 28, 'balanced'],
            'opportunity' => [1.10, 1, 45, 40, 'opportunity'],
            'positive but exceptionally risky' => [1.10, 1, 30, 60, 'risk'],
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
