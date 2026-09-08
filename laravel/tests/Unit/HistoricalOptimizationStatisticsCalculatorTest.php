<?php

namespace Tests\Unit;

use App\Services\HistoricalOptimizationStatisticsCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HistoricalOptimizationStatisticsCalculatorTest extends TestCase
{
    #[Test]
    public function it_uses_gross_return_and_applies_each_fixed_fee_exactly_once(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(1, '2026-01-01', '2026-01-02', .10, [
                'net_return' => -.75,
                'historical_action_score' => 80,
            ]),
        ], 1000, 10);

        // Ninety-nine whole shares use the entire 990 euro investable budget.
        // Profit is 99 less the two 10 euro fees, so the return on the complete
        // 1,000 euro entry budget is 7.9%.
        $this->assertEqualsWithDelta(.079, $result['returns']->sole(), 0.000000001);
        $this->assertEqualsWithDelta(7.9, $result['average_return'], 0.000000001);
        $this->assertSame(100.0, $result['hit_rate']);
        $this->assertSame(80.0, $result['signal_quality']);
    }

    #[Test]
    public function it_orders_deterministically_and_keeps_the_exit_date_blocked(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(5, '2026-01-11', '2026-01-12', .05),
            $this->trade(2, '2026-01-01', '2026-01-02', .90),
            $this->trade(4, '2026-01-10', '2026-01-11', .80),
            $this->trade(3, '2026-01-05', '2026-01-06', .70),
            $this->trade(1, '2026-01-01', '2026-01-10', .10),
        ], 1000, 0);

        $this->assertSame([1, 5], $result['selected_trades']->pluck('trade_id')->all());
        $this->assertSame(2, $result['trades']);
        $this->assertSame(3, $result['skipped_overlap']);
    }

    #[Test]
    public function it_returns_the_capped_profit_factor_when_there_are_wins_and_no_losses(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(1, '2026-01-01', '2026-01-02', .10),
            $this->trade(2, '2026-01-03', '2026-01-04', .20),
        ], 1000, 0);

        $this->assertSame(3.0, $result['profit_factor']);
    }

    #[Test]
    public function it_calculates_drawdown_from_compounded_returns(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(1, '2026-01-01', '2026-01-02', 1.0),
            $this->trade(2, '2026-01-03', '2026-01-04', -.60),
        ], 1000, 0);

        $this->assertEqualsWithDelta(60.0, $result['drawdown'], 0.000000001);
    }

    #[Test]
    public function it_keeps_uninvested_remainder_cash_out_of_the_trade_return(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(1, '2026-01-01', '2026-01-02', .10, ['entry_price' => 333]),
        ], 1000, 10);

        // Only two shares can be bought: 666 * 10% - 20 fees = 46.60 profit.
        // The remaining cash does not participate in the stock return.
        $this->assertEqualsWithDelta(.0466, $result['returns']->sole(), 0.000000001);
        $this->assertEqualsWithDelta(4.66, $result['average_return'], 0.000000001);
    }

    #[Test]
    public function an_unaffordable_candidate_does_not_block_a_later_affordable_trade(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(1, '2026-01-01', '2026-02-01', .50, ['entry_price' => 1000]),
            $this->trade(2, '2026-01-02', '2026-01-03', .10, ['entry_price' => 100]),
        ], 1000, 10);

        $this->assertSame([2], $result['selected_trades']->pluck('trade_id')->all());
        $this->assertSame(1, $result['trades']);
        $this->assertSame(1, $result['skipped_unaffordable']);
        $this->assertSame(0, $result['skipped_overlap']);
    }

    #[Test]
    public function it_rejects_a_budget_that_cannot_cover_more_than_the_entry_fee(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator()->calculate([], 10, 10);
    }

    private function calculator(): HistoricalOptimizationStatisticsCalculator
    {
        return app(HistoricalOptimizationStatisticsCalculator::class);
    }

    /** @param array<string, mixed> $overrides */
    private function trade(
        int $id,
        string $signalDate,
        string $exitDate,
        float $grossReturn,
        array $overrides = [],
    ): object {
        return (object) array_replace([
            'trade_id' => $id,
            'instrument_id' => 1,
            'horizon_days' => 20,
            'signal_date' => $signalDate,
            'exit_date' => $exitDate,
            'gross_return' => $grossReturn,
            'net_return' => $grossReturn,
            'historical_action_score' => 50,
            'entry_price' => 10,
        ], $overrides);
    }
}
