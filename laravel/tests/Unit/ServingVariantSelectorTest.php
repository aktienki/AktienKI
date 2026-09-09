<?php

namespace Tests\Unit;

use App\Services\ServingVariantSelector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ServingVariantSelectorTest extends TestCase
{
    #[Test]
    public function it_promotes_a_quality_gated_tcn_when_standard_failed(): void
    {
        self::assertSame('pure_tcn', ServingVariantSelector::select($this->horizon(
            standardGate: false,
            tcnGate: true,
        )));
    }

    #[Test]
    public function it_promotes_tcn_when_both_gates_pass_and_both_performance_metrics_improve(): void
    {
        self::assertSame('pure_tcn', ServingVariantSelector::select($this->horizon(
            standardGate: true,
            tcnGate: true,
            standardReturn: .01,
            standardProfitFactor: 2.1,
            tcnReturn: .025,
            tcnProfitFactor: 3.2,
        )));
    }

    #[Test]
    public function it_keeps_standard_when_tcn_does_not_pass_or_is_not_clearly_better(): void
    {
        self::assertSame('standard', ServingVariantSelector::select($this->horizon(
            standardGate: true,
            tcnGate: false,
        )));

        self::assertSame('standard', ServingVariantSelector::select($this->horizon(
            standardGate: true,
            tcnGate: true,
            standardReturn: .02,
            standardProfitFactor: 2.5,
            tcnReturn: .03,
            tcnProfitFactor: 2.4,
        )));
    }

    private function horizon(
        bool $standardGate,
        bool $tcnGate,
        float $standardReturn = .01,
        float $standardProfitFactor = 2.0,
        float $tcnReturn = .02,
        float $tcnProfitFactor = 3.0,
    ): array {
        $variant = static fn (bool $gate, float $return, float $profitFactor): array => [
            'metrics' => [
                'average_net_trade' => $return,
                'profit_factor' => $profitFactor,
            ],
            'prediction_status' => [
                'prediction_enabled' => true,
                'quality_gate' => ['passed' => $gate],
            ],
        ];

        return [
            'standard' => $variant($standardGate, $standardReturn, $standardProfitFactor),
            'pure_tcn' => $variant($tcnGate, $tcnReturn, $tcnProfitFactor),
        ];
    }
}
