<?php

namespace Tests\Unit;

use App\Services\HistoricalIndicatorMatrixService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

final class HistoricalIndicatorMatrixServiceTest extends TestCase
{
    #[DataProvider('phaseCases')]
    public function test_market_phase_presets_use_macd_and_stochastic_together(array $point, string $preset, bool $expected): void
    {
        $method = new ReflectionMethod(HistoricalIndicatorMatrixService::class, 'matches');
        $this->assertSame($expected, $method->invoke(new HistoricalIndicatorMatrixService(), (object) $point, [
            'indicator_matrix_preset' => $preset,
        ]));
    }

    public static function phaseCases(): array
    {
        return [
            'oversold recovery' => [['macd_percent' => -.10, 'previous_macd_percent' => -.16, 'stochastic_k' => 18], 'oversold_recovery', true],
            'oversold but falling' => [['macd_percent' => -.20, 'previous_macd_percent' => -.16, 'stochastic_k' => 18], 'oversold_recovery', false],
            'bullish impulse' => [['macd_percent' => .12, 'previous_macd_percent' => .08, 'stochastic_k' => 66], 'bullish_impulse', true],
            'overheated fading' => [['macd_percent' => .08, 'previous_macd_percent' => .13, 'stochastic_k' => 86], 'overheated_fading', true],
            'bearish impulse' => [['macd_percent' => -.12, 'previous_macd_percent' => -.08, 'stochastic_k' => 42], 'bearish_impulse', true],
        ];
    }

    public function test_manual_matrix_honours_min_max_and_direction(): void
    {
        $method = new ReflectionMethod(HistoricalIndicatorMatrixService::class, 'matches');
        $matches = $method->invoke(new HistoricalIndicatorMatrixService(), (object) [
            'macd_percent' => .12, 'previous_macd_percent' => .08, 'stochastic_k' => 64,
        ], [
            'indicator_matrix_preset' => 'manual',
            'indicator_matrix_macd_min' => .10,
            'indicator_matrix_macd_max' => .20,
            'indicator_matrix_stoch_min' => 55,
            'indicator_matrix_stoch_max' => 70,
            'indicator_matrix_macd_direction' => 'rising',
        ]);
        $this->assertTrue($matches);
    }
}
