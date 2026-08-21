<?php

namespace Tests\Unit;

use App\Services\MacdStochasticMarketPhaseService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MacdStochasticMarketPhaseServiceTest extends TestCase
{
    #[DataProvider('phases')]
    public function test_it_classifies_market_phases(
        float $macd,
        float $previousMacd,
        float $stochastic,
        float $previousStochastic,
        string $expected,
        float $adjustment,
        bool $veto,
    ): void {
        $phase = (new MacdStochasticMarketPhaseService)->classify($macd, $previousMacd, $stochastic, $previousStochastic);

        $this->assertSame($expected, $phase['key']);
        $this->assertSame($adjustment, $phase['score_adjustment']);
        $this->assertSame($veto, $phase['buy_veto']);
    }

    public static function phases(): array
    {
        return [
            'bullish impulse' => [.5, .3, 65, 55, 'bullish_impulse', 4.0, false],
            'overheated fading' => [.3, .5, 85, 88, 'overheated_fading', -12.0, true],
            'early recovery' => [-.2, -.4, 40, 30, 'early_recovery', 7.0, false],
            'bearish impulse' => [-.5, -.3, 35, 40, 'bearish_impulse', 3.0, false],
            'oversold stabilization' => [-.2, -.4, 15, 18, 'oversold_stabilizing', 8.0, false],
            'mature trend' => [.4, .3, 85, 82, 'mature_uptrend', 0.0, false],
            'negative divergence' => [-.2, -.1, 60, 65, 'negative_divergence', 8.0, false],
            'neutral' => [.2, .3, 60, 55, 'neutral_transition', 0.0, false],
        ];
    }
}
