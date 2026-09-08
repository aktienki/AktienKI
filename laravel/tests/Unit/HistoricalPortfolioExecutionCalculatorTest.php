<?php

namespace Tests\Unit;

use App\Services\HistoricalPortfolioExecutionCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HistoricalPortfolioExecutionCalculatorTest extends TestCase
{
    #[Test]
    public function it_opens_five_parallel_positions_when_the_fee_is_part_of_each_budget(): void
    {
        $candidates = [];

        foreach (range(1, 5) as $id) {
            $candidates[] = $this->trade(
                id: $id,
                instrumentId: $id,
                entryDate: '2023-01-02',
                exitDate: '2023-01-20',
                entryPrice: 100,
                exitPrice: 110,
            );
        }

        $result = $this->calculator()->calculate($candidates, 10000, 5, 2000, 10);

        $this->assertSame(5, $result['trade_count']);
        $this->assertSame(0, $result['skipped_due_cash']);
        $this->assertSame(0, $result['skipped_due_capacity']);
        $this->assertSame([19, 19, 19, 19, 19], array_column($result['trade_log'], 'quantity'));
        $this->assertSame([1910.0, 1910.0, 1910.0, 1910.0, 1910.0], array_column($result['trade_log'], 'entry_debit'));
        $this->assertSame(100.0, $result['total_costs']);
        $this->assertEqualsWithDelta(.5, $result['max_drawdown_percent'], .000000001);
    }

    #[Test]
    public function it_calculates_the_exact_cash_flows_and_net_return_for_whole_shares(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(entryPrice: 100, exitPrice: 110),
        ], 10000, 1, 10000, 10);

        $trade = $result['trade_log'][0];

        $this->assertSame(99, $trade['quantity']);
        $this->assertSame(9900.0, $trade['allocated_capital_eur']);
        $this->assertSame(9910.0, $trade['entry_debit']);
        $this->assertSame(10880.0, $trade['exit_credit']);
        $this->assertSame(970.0, $trade['profit_eur']);
        $this->assertEqualsWithDelta(970 / 9910, $trade['net_return_after_cost'], 0.000000000001);
        $this->assertEqualsWithDelta(0.1 - (970 / 9910), $trade['transaction_cost_return'], 0.000000000001);
        $this->assertSame(10970.0, $result['final_cash']);
        $this->assertSame(20.0, $result['total_costs']);
    }

    #[Test]
    public function it_never_uses_fractional_shares(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(entryPrice: 333, exitPrice: 350),
        ], 1000, 1, 1000, 10);

        $trade = $result['trade_log'][0];

        $this->assertSame(2, $trade['quantity']);
        $this->assertSame(666.0, $trade['allocated_capital_eur']);
        $this->assertSame(676.0, $trade['entry_debit']);
    }

    #[Test]
    public function an_exit_on_the_entry_date_of_another_signal_still_blocks_that_instrument(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(id: 1, entryDate: '2023-01-01', exitDate: '2023-01-02'),
            $this->trade(id: 2, entryDate: '2023-01-02', exitDate: '2023-01-10'),
        ], 10000, 5, 2000, 10);

        $this->assertSame(1, $result['trade_count']);
        $this->assertSame(1, $result['skipped_due_same_instrument_open']);
    }

    #[Test]
    public function dynamic_weighting_compounds_realized_portfolio_growth(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(id: 1, entryDate: '2023-01-01', exitDate: '2023-01-02'),
            $this->trade(id: 2, entryDate: '2023-01-03', exitDate: '2023-01-04'),
        ], 10000, 1, 10000, 0, true);

        $this->assertSame(2, $result['trade_count']);
        $this->assertSame(100, $result['trade_log'][0]['quantity']);
        $this->assertSame(110, $result['trade_log'][1]['quantity']);
        $this->assertSame(1.1, $result['trade_log'][1]['dynamic_capital_factor']);
        $this->assertSame(12100.0, $result['final_cash']);
    }

    #[Test]
    public function dynamic_weighting_reduces_the_budget_after_realized_losses(): void
    {
        $result = $this->calculator()->calculate([
            $this->trade(id: 1, entryDate: '2023-01-01', exitDate: '2023-01-02', entryPrice: 100, exitPrice: 50, grossReturn: -.50),
            $this->trade(id: 2, entryDate: '2023-01-03', exitDate: '2023-01-04', entryPrice: 100, exitPrice: 100, grossReturn: 0),
        ], 10000, 5, 2000, 0, true);

        $this->assertSame(2, $result['trade_count']);
        $this->assertSame(20, $result['trade_log'][0]['quantity']);
        $this->assertSame(18, $result['trade_log'][1]['quantity']);
        $this->assertSame(.9, $result['trade_log'][1]['dynamic_capital_factor']);
        $this->assertSame(1800.0, $result['trade_log'][1]['effective_target_position_budget']);
    }

    #[Test]
    public function it_skips_candidates_without_valid_prices(): void
    {
        $arrayCandidate = $this->trade(id: 1, entryPrice: 0);
        $objectCandidate = (object) $this->trade(id: 2, entryPrice: 100, exitPrice: -1);

        $result = $this->calculator()->calculate([$arrayCandidate, $objectCandidate], 10000, 5, 2000, 10);

        $this->assertSame(0, $result['trade_count']);
        $this->assertSame(2, $result['skipped_due_invalid_price']);
        $this->assertSame(10000.0, $result['final_cash']);
    }

    private function calculator(): HistoricalPortfolioExecutionCalculator
    {
        return app(HistoricalPortfolioExecutionCalculator::class);
    }

    /** @return array<string, int|float|string> */
    private function trade(
        int $id = 1,
        int $instrumentId = 1,
        string $entryDate = '2023-01-02',
        string $exitDate = '2023-01-20',
        float $entryPrice = 100,
        float $exitPrice = 110,
        float $grossReturn = 0.1,
    ): array {
        return [
            'id' => $id,
            'instrument_id' => $instrumentId,
            'entry_date' => $entryDate,
            'exit_date' => $exitDate,
            'entry_price' => $entryPrice,
            'exit_price' => $exitPrice,
            'gross_return' => $grossReturn,
        ];
    }
}
