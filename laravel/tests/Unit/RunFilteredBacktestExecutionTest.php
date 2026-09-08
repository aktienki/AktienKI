<?php

namespace Tests\Unit;

use App\Http\Controllers\PredictionController;
use App\Jobs\RunFilteredBacktest;
use Tests\TestCase;

final class RunFilteredBacktestExecutionTest extends TestCase
{
    public function test_empty_serving_configuration_list_uses_the_normal_backtest_source(): void
    {
        $empty = new RunFilteredBacktest(1, 1, ['serving_model_configurations' => []]);
        $selected = new RunFilteredBacktest(1, 1, ['serving_model_configurations' => [['symbol' => 'PUM.DE']]]);
        $usesServing = fn (): bool => $this->usesServingConfigurations();

        $this->assertFalse(\Closure::bind($usesServing, $empty, $empty)());
        $this->assertTrue(\Closure::bind($usesServing, $selected, $selected)());
    }

    public function test_same_instrument_stays_blocked_through_its_horizon_exit_date(): void
    {
        $job = $this->job(maxPositions: 5);

        [$executed, $summary] = $this->select($job, collect([
            $this->trade(1, 11, '2026-01-01', '2026-01-10'),
            $this->trade(2, 22, '2026-01-01', '2026-01-04'),
            $this->trade(3, 11, '2026-01-05', '2026-01-08'),
            $this->trade(4, 11, '2026-01-10', '2026-01-20'),
            $this->trade(5, 11, '2026-01-11', '2026-01-20'),
        ]));

        $this->assertSame([1, 2, 5], $executed->pluck('id')->all());
        $this->assertSame(2, $summary['excluded_same_instrument_overlap']);
        $this->assertSame(0, $summary['excluded_capacity_or_cash']);
        $this->assertSame(0, $summary['excluded_invalid_holding_period']);
    }

    public function test_parallel_instruments_are_limited_by_real_portfolio_capacity(): void
    {
        $job = $this->job(maxPositions: 1);

        [$executed, $summary] = $this->select($job, collect([
            $this->trade(1, 11, '2026-01-01', '2026-01-10'),
            $this->trade(2, 22, '2026-01-02', '2026-01-05'),
            $this->trade(3, 22, '2026-01-11', '2026-01-20'),
        ]));

        $this->assertSame([1, 3], $executed->pluck('id')->all());
        $this->assertSame(0, $summary['excluded_same_instrument_overlap']);
        $this->assertSame(1, $summary['excluded_capacity_or_cash']);
    }

    public function test_dynamic_weighting_reinvests_free_realized_capital(): void
    {
        $job = new RunFilteredBacktest(1, 1, [
            'initial_capital' => 10000,
            'max_positions' => 5,
            'position_factor' => 5,
            'dynamic_capital_weighting' => 1,
            'trade_cost' => 0,
        ]);
        $first = $this->trade(1, 11, '2026-01-01', '2026-01-02');
        $first->gross_return = .10;
        $first->exit_price = 110.0;
        $second = $this->trade(2, 22, '2026-01-03', '2026-01-04');
        $second->gross_return = .10;
        $second->exit_price = 110.0;

        [$executed, $summary] = $this->select($job, collect([$first, $second]));

        $this->assertSame([10000.0, 11000.0], $executed->pluck('allocated_capital_eur')->all());
        $this->assertEqualsWithDelta(12100, $summary['cash'], .001);
    }

    public function test_worker_uses_the_exact_whole_share_cash_ledger(): void
    {
        $job = new RunFilteredBacktest(1, 1, [
            'initial_capital' => 10000,
            'max_positions' => 1,
            'position_factor' => 1,
            'trade_cost' => 10,
        ]);

        [$executed, $summary] = $this->select($job, collect([
            $this->trade(1, 11, '2026-01-01', '2026-01-10', 100, 110, .10),
        ]));
        $trade = $executed->first();

        $this->assertSame(99, $trade->quantity);
        $this->assertSame(9910.0, $trade->entry_debit);
        $this->assertSame(10880.0, $trade->exit_credit);
        $this->assertSame(970.0, $trade->profit_eur);
        $this->assertEqualsWithDelta(970 / 9910, $trade->net_return_after_cost, .000000000001);
        $this->assertSame(10970.0, $summary['cash']);
        $this->assertSame(20.0, $summary['total_costs']);
    }

    public function test_worker_and_result_controller_use_the_same_dynamic_ledger(): void
    {
        $filters = [
            'initial_capital' => 10000,
            'max_positions' => 1,
            'position_factor' => 1,
            'dynamic_capital_weighting' => 1,
            'trade_cost' => 10,
        ];
        $candidates = collect([
            $this->trade(1, 11, '2026-01-01', '2026-01-02', 100, 110, .10),
            $this->trade(2, 22, '2026-01-03', '2026-01-04', 100, 110, .10),
        ]);
        [$workerTrades, $workerSummary] = $this->select(new RunFilteredBacktest(1, 1, $filters), $candidates);

        $controller = app(PredictionController::class);
        $simulate = \Closure::bind(
            fn ($rows): array => $this->simulatePortfolio(
                $rows,
                10000,
                1,
                1,
                10,
                '2026-01-01',
                '2026-01-04',
                true,
            ),
            $controller,
            $controller,
        );
        $result = $simulate($candidates);

        $this->assertSame($workerTrades->count(), $result['executed']);
        $this->assertEqualsWithDelta($workerSummary['cash'], $result['final'], .001);
        $this->assertSame($workerSummary['total_costs'], $result['total_costs']);
    }

    private function job(int $maxPositions): RunFilteredBacktest
    {
        return new RunFilteredBacktest(1, 1, [
            'initial_capital' => 10000,
            'max_positions' => $maxPositions,
            'position_factor' => 1,
            'trade_cost' => 0,
        ]);
    }

    private function select(RunFilteredBacktest $job, $candidates): array
    {
        $selector = \Closure::bind(
            fn ($rows): array => $this->capitalConstrainedTrades($rows),
            $job,
            $job,
        );

        return $selector($candidates);
    }

    private function trade(
        int $id,
        int $instrumentId,
        string $entryDate,
        string $exitDate,
        float $entryPrice = 100,
        float $exitPrice = 101,
        float $grossReturn = .01,
    ): object {
        return (object) [
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
