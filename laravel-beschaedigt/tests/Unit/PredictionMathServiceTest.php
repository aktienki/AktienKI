<?php
namespace Tests\Unit;

use App\Services\PredictionMathService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PredictionMathServiceTest extends TestCase
{
    private PredictionMathService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PredictionMathService();
    }

    public function test_positive_long_return_for_rising_prices(): void
    {
        $result = $this->service->calculate(
            currentPrice: 100.0,
            predictedPrice: 110.0,
        );

        $this->assertSame(10.0, $result['price_difference']);
        $this->assertEqualsWithDelta(
            0.10,
            $result['market_return'],
            0.0000001,
        );
        $this->assertEqualsWithDelta(
            0.10,
            $result['long_return'],
            0.0000001,
        );
        $this->assertEqualsWithDelta(
            -0.10,
            $result['short_return'],
            0.0000001,
        );
        $this->assertSame('long', $result['strategy']);
        $this->assertEqualsWithDelta(
            0.10,
            $result['strategy_return'],
            0.0000001,
        );
    }

    public function test_positive_short_return_for_falling_prices(): void
    {
        $result = $this->service->calculate(
            currentPrice: 100.0,
            predictedPrice: 90.0,
        );

        $this->assertSame(-10.0, $result['price_difference']);
        $this->assertEqualsWithDelta(
            -0.10,
            $result['market_return'],
            0.0000001,
        );
        $this->assertEqualsWithDelta(
            -0.10,
            $result['long_return'],
            0.0000001,
        );
        $this->assertEqualsWithDelta(
            0.10,
            $result['short_return'],
            0.0000001,
        );
        $this->assertSame('short', $result['strategy']);
        $this->assertEqualsWithDelta(
            0.10,
            $result['strategy_return'],
            0.0000001,
        );
    }

    public function test_unchanged_price_has_zero_return(): void
    {
        $result = $this->service->calculate(
            currentPrice: 100.0,
            predictedPrice: 100.0,
        );

        $this->assertSame(0.0, $result['price_difference']);
        $this->assertSame(0.0, $result['market_return']);
        $this->assertSame(0.0, $result['strategy_return']);
    }

    public function test_zero_current_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculate(
            currentPrice: 0.0,
            predictedPrice: 100.0,
        );
    }

    public function test_negative_current_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculate(
            currentPrice: -100.0,
            predictedPrice: 90.0,
        );
    }
}
