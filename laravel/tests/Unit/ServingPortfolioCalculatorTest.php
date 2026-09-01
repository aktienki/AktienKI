<?php

namespace Tests\Unit;

use App\Services\ServingPortfolioCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ServingPortfolioCalculatorTest extends TestCase
{
    #[Test]
    public function it_balances_capital_across_the_target_number_of_stocks_and_reconciles_the_model_reference(): void
    {
        $result = app(ServingPortfolioCalculator::class)->calculate([
            $this->source([
                $this->trade(1, '2023-01-02', '2023-01-20', 100, 110, 0.098),
                $this->trade(2, '2023-02-01', '2023-02-20', 100, 90, -0.102),
            ], [
                'trades' => 2,
                'hit_rate' => 0.5,
                'profit_factor' => 0.9607843137,
                'average_net_trade' => -0.002,
                'cumulative_return' => -0.013996,
                'max_drawdown' => -0.102,
            ]),
        ], 10000);

        $this->assertSame(2, $result['trades']);
        $this->assertSame(9956.42, $result['final_capital']);
        $this->assertSame(-0.44, $result['performance_percent']);
        $this->assertSame(40.0, $result['total_costs']);
        $this->assertSame(1990.0, $result['trade_log'][0]['position_notional']);
        $this->assertSame(19.9, $result['trade_log'][0]['quantity']);
        $this->assertSame(179.0, $result['trade_log'][0]['profit']);
        $this->assertSame(-222.58, $result['trade_log'][1]['profit']);
        $this->assertSame(ServingPortfolioCalculator::ALLOCATION_EQUAL_WEIGHT, $result['allocation_mode']);
        $this->assertSame(2000.0, $result['initial_equal_weight_budget']);
        $this->assertSame(30.0, $result['max_stock_allocation_percent']);
        $this->assertSame(-1.4, $result['model_reference_performance_percent']);
        $this->assertSame(9860.04, $result['model_reference_final_capital']);
    }

    #[Test]
    public function it_ignores_the_balancing_cap_and_uses_all_cash_in_full_investment_mode(): void
    {
        $result = app(ServingPortfolioCalculator::class)->calculate([
            $this->source([
                $this->trade(1, '2023-01-02', '2023-01-20', 100, 200, 0.998),
            ], [
                'trades' => 1,
                'hit_rate' => 1.0,
                'profit_factor' => INF,
                'average_net_trade' => 0.998,
                'cumulative_return' => 0.998,
                'max_drawdown' => 0.0,
            ]),
        ], 10000, 5, ServingPortfolioCalculator::ALLOCATION_FULL_INVESTMENT, 0.30);

        $this->assertEqualsWithDelta(9970.09, $result['trade_log'][0]['position_notional'], 0.01);
        $this->assertSame(29.91, $result['trade_log'][0]['buy_fee']);
        $this->assertSame(59.82, $result['trade_log'][0]['sell_fee']);
        $this->assertSame(89.73, $result['total_costs']);
        $this->assertEqualsWithDelta(9880.36, $result['trade_log'][0]['profit'], 0.01);
        $this->assertSame(100.0, $result['effective_max_stock_allocation_percent']);
        $this->assertSame(1, $result['maximum_positions']);
        $this->assertSame(5, $result['requested_maximum_positions']);
    }

    #[Test]
    public function it_uses_all_available_cash_in_full_investment_mode_up_to_the_stock_limit(): void
    {
        $result = app(ServingPortfolioCalculator::class)->calculate([
            $this->source([
                $this->trade(1, '2023-01-02', '2023-01-20', 100, 110, 0.098),
            ], [
                'trades' => 1,
                'hit_rate' => 1.0,
                'profit_factor' => INF,
                'average_net_trade' => 0.098,
                'cumulative_return' => 0.098,
                'max_drawdown' => 0.0,
            ]),
        ], 10000, 5, ServingPortfolioCalculator::ALLOCATION_FULL_INVESTMENT, 1.0);

        $this->assertEqualsWithDelta(9970.09, $result['trade_log'][0]['position_notional'], 0.01);
        $this->assertSame(29.91, $result['trade_log'][0]['buy_fee']);
        $this->assertSame(10934.2, $result['final_capital']);
        $this->assertSame(100.0, $result['max_stock_allocation_percent']);
        $this->assertNull($result['initial_equal_weight_budget']);
    }

    #[Test]
    public function it_reserves_equal_capital_budgets_for_parallel_stock_signals(): void
    {
        $metrics = [
            'trades' => 1,
            'hit_rate' => 1.0,
            'profit_factor' => INF,
            'average_net_trade' => 0.098,
            'cumulative_return' => 0.098,
            'max_drawdown' => 0.0,
        ];
        $result = app(ServingPortfolioCalculator::class)->calculate([
            $this->source([
                $this->trade(1, '2023-01-02', '2023-01-20', 100, 110, 0.098),
            ], $metrics, 'ONE.DE'),
            $this->source([
                $this->trade(2, '2023-01-02', '2023-01-20', 50, 55, 0.098),
            ], $metrics, 'TWO.DE'),
        ], 10000, 2, ServingPortfolioCalculator::ALLOCATION_EQUAL_WEIGHT, 1.0);

        $this->assertSame(2, $result['trades']);
        $this->assertSame(0, $result['skipped_due_cash']);
        $this->assertEqualsWithDelta(
            $result['trade_log'][0]['position_notional'],
            $result['trade_log'][1]['position_notional'],
            0.01,
        );
        $this->assertSame(['ONE.DE', 'TWO.DE'], collect($result['trade_log'])->pluck('symbol')->sort()->values()->all());
    }

    #[Test]
    public function it_limits_each_balanced_position_to_the_selected_stock_allocation(): void
    {
        $result = app(ServingPortfolioCalculator::class)->calculate([
            $this->source([
                $this->trade(1, '2023-01-02', '2023-01-20', 100, 110, 0.098),
            ], [
                'trades' => 1,
                'hit_rate' => 1.0,
                'profit_factor' => INF,
                'average_net_trade' => 0.098,
                'cumulative_return' => 0.098,
                'max_drawdown' => 0.0,
            ]),
        ], 10000, 2, ServingPortfolioCalculator::ALLOCATION_EQUAL_WEIGHT, 0.10);

        $this->assertSame(1000.0, $result['trade_log'][0]['position_notional']);
        $this->assertSame(10.0, $result['trade_log'][0]['buy_fee']);
        $this->assertSame(10.0, $result['max_stock_allocation_percent']);
        $this->assertSame(10.0, $result['effective_max_stock_allocation_percent']);
    }

    #[Test]
    public function it_rejects_a_second_position_in_the_same_stock_until_the_first_exit(): void
    {
        $result = app(ServingPortfolioCalculator::class)->calculate([
            $this->source([
                $this->trade(1, '2023-01-02', '2023-02-20', 100, 110, 0.098),
                $this->trade(2, '2023-01-10', '2023-01-30', 100, 105, 0.048),
            ], [
                'trades' => 2,
                'hit_rate' => 1.0,
                'profit_factor' => INF,
                'average_net_trade' => 0.073,
                'cumulative_return' => 0.150704,
                'max_drawdown' => 0.0,
            ]),
        ], 10000);

        $this->assertSame(1, $result['trades']);
        $this->assertSame(1, $result['skipped_due_same_stock_open']);
        $this->assertSame(0, $result['skipped_due_capacity']);
    }

    #[Test]
    public function it_rejects_model_returns_that_do_not_match_prices_and_stored_costs(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Kurse, Modellkosten und Nettoertrag');

        app(ServingPortfolioCalculator::class)->calculate([
            $this->source([
                $this->trade(1, '2023-01-02', '2023-01-20', 100, 110, 0.05),
            ], [
                'trades' => 1,
                'hit_rate' => 1.0,
                'profit_factor' => INF,
                'average_net_trade' => 0.05,
                'cumulative_return' => 0.05,
                'max_drawdown' => 0.0,
            ]),
        ], 10000);
    }

    /**
     * @param  array<int, array<string, mixed>>  $trades
     * @param  array<string, float|int>  $metrics
     * @return array<string, mixed>
     */
    private function source(array $trades, array $metrics, string $symbol = 'TEST.DE'): array
    {
        return [
            'configuration_key' => 'release:'.$symbol.':40:pure_tcn',
            'release_id' => 'release',
            'symbol' => $symbol,
            'instrument_id' => 1,
            'strategy_id' => 10,
            'strategy_name' => 'TEST.DE · 40T · Pure TCN',
            'priority' => 10,
            'horizon_days' => 40,
            'variant' => 'pure_tcn',
            'variant_label' => 'Pure TCN',
            'model_name' => 'Temporal Convolutional Network',
            'quality_label' => 'Top',
            'strategy_run_id' => 'run',
            'strategy' => 'pure-tcn-calibrated-fixed-horizon-oos-v3',
            'source_metrics' => $metrics,
            'stored_transaction_cost' => 0.002,
            'trades' => $trades,
        ];
    }

    /** @return array<string, mixed> */
    private function trade(
        int $id,
        string $entryDate,
        string $exitDate,
        float $entryPrice,
        float $exitPrice,
        float $netReturn,
    ): array {
        return [
            'serving_strategy_trade_id' => $id,
            'entry_signal' => 'BUY',
            'entry_date' => $entryDate,
            'entry_price' => $entryPrice,
            'exit_date' => $exitDate,
            'exit_price' => $exitPrice,
            'holding_days' => 40,
            'net_return' => $netReturn,
            'transaction_cost' => 0.002,
            'exit_reason' => 'MAX_HOLDING_DAYS',
            'strategy_run_id' => 'run',
            'strategy' => 'pure-tcn-calibrated-fixed-horizon-oos-v3',
        ];
    }
}
