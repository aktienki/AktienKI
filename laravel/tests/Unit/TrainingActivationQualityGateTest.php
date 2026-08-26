<?php

namespace Tests\Unit;

use App\Services\TrainingActivationQualityGate;
use Tests\TestCase;

final class TrainingActivationQualityGateTest extends TestCase
{
    public function test_it_accepts_metrics_on_the_configured_boundaries(): void
    {
        $this->assertTrue(app(TrainingActivationQualityGate::class)->passes([
            'direction_accuracy' => 0.55,
            'profit_factor' => 1.30,
            'trade_count' => 15,
            'max_drawdown' => 0.40,
        ]));
    }

    public function test_it_rejects_a_model_when_any_required_metric_fails(): void
    {
        $valid = [
            'direction_accuracy' => 0.55,
            'profit_factor' => 1.30,
            'trade_count' => 15,
            'max_drawdown' => 0.40,
        ];

        $failingMetrics = [
            'hit rate' => [array_replace($valid, ['direction_accuracy' => 0.549])],
            'profit factor' => [array_replace($valid, ['profit_factor' => 1.299])],
            'trades' => [array_replace($valid, ['trade_count' => 14])],
            'drawdown' => [array_replace($valid, ['max_drawdown' => 0.401])],
            'missing metric' => [array_diff_key($valid, ['profit_factor' => true])],
        ];

        $qualityGate = app(TrainingActivationQualityGate::class);
        foreach ($failingMetrics as $case => [$metrics]) {
            $this->assertFalse($qualityGate->passes($metrics), $case);
        }
    }

    public function test_it_accepts_ten_trades_at_sixty_five_percent_direction_accuracy(): void
    {
        $this->assertTrue(app(TrainingActivationQualityGate::class)->passes([
            'direction_accuracy' => 0.65,
            'profit_factor' => 1.30,
            'trade_count' => 10,
            'max_drawdown' => 0.40,
        ]));
    }

    public function test_it_still_requires_fifteen_trades_below_sixty_five_percent_direction_accuracy(): void
    {
        $this->assertFalse(app(TrainingActivationQualityGate::class)->passes([
            'direction_accuracy' => 0.649,
            'profit_factor' => 1.30,
            'trade_count' => 14,
            'max_drawdown' => 0.40,
        ]));
    }
}
