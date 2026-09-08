<?php

namespace Tests\Unit;

use App\Services\ServingPortfolioCalculator;
use App\Services\StockSpecificExitPortfolioSimulationService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class StockSpecificExitPortfolioSimulationServiceTest extends TestCase
{
    #[Test]
    public function it_keeps_the_existing_strategy_exit_and_both_comparable_exit_policies_selectable(): void
    {
        $this->assertSame([
            'stock_specific_final_exit',
            'fixed_horizon_20t',
            'strategy_default',
        ], array_keys(StockSpecificExitPortfolioSimulationService::selectablePolicies()));
        $this->assertTrue(StockSpecificExitPortfolioSimulationService::isPreparedExitPolicy('stock_specific_final_exit'));
        $this->assertTrue(StockSpecificExitPortfolioSimulationService::isPreparedExitPolicy('fixed_horizon_20t'));
        $this->assertFalse(StockSpecificExitPortfolioSimulationService::isPreparedExitPolicy('strategy_default'));
    }

    #[Test]
    public function it_maps_frozen_sources_to_local_instruments_and_a_real_assigned_strategy(): void
    {
        $service = new StockSpecificExitPortfolioSimulationService(new ServingPortfolioCalculator);
        $sources = $service->prepareSources(
            [$this->source()],
            collect([
                (object) ['saved_prediction_filter_id' => 20, 'strategy_name' => 'Zweite', 'priority' => 20],
                (object) ['saved_prediction_filter_id' => 10, 'strategy_name' => 'Finaler BUY-Filter', 'priority' => 10],
            ]),
            collect(['TEST.DE' => 987]),
            StockSpecificExitPortfolioSimulationService::STOCK_SPECIFIC_FINAL_EXIT,
            str_repeat('a', 64),
        );

        $this->assertCount(1, $sources);
        $this->assertSame(987, $sources[0]['instrument_id']);
        $this->assertSame(10, $sources[0]['strategy_id']);
        $this->assertSame(10, $sources[0]['priority']);
        $this->assertSame(
            'Finaler BUY-Filter · Aktienspezifischer Exit',
            $sources[0]['strategy_name'],
        );
        $this->assertSame(
            'filtered-entry-exit-v1:stock_specific_final_exit:TEST.DE:aaaaaaaaaaaa',
            $sources[0]['configuration_key'],
        );
        $this->assertSame(7, $sources[0]['trades'][0]['holding_days']);
        $this->assertSame('frozen-run', $sources[0]['trades'][0]['strategy_run_id']);
        $this->assertSame('stock_specific_final_exit', $sources[0]['trades'][0]['strategy']);

        $result = (new ServingPortfolioCalculator)->calculate(
            $sources,
            10000,
            1,
            ServingPortfolioCalculator::ALLOCATION_EQUAL_WEIGHT,
            1.0,
            0.003,
            10,
        );
        $this->assertSame(1, $result['trades']);
        $this->assertSame('STOCK_SPECIFIC_SELECTED_EXIT', $result['trade_log'][0]['exit_reason']);
        $this->assertSame(7, $result['trade_log'][0]['holding_days']);
    }

    #[Test]
    public function it_fails_closed_when_a_frozen_symbol_has_no_local_stock_reference(): void
    {
        $service = new StockSpecificExitPortfolioSimulationService(new ServingPortfolioCalculator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('keine aktive Aktienreferenz für TEST.DE');

        $service->prepareSources(
            [$this->source()],
            collect([(object) [
                'saved_prediction_filter_id' => 10,
                'strategy_name' => 'Test',
                'priority' => 10,
            ]]),
            new Collection,
            StockSpecificExitPortfolioSimulationService::STOCK_SPECIFIC_FINAL_EXIT,
            str_repeat('b', 64),
        );
    }

    #[Test]
    public function it_verifies_checksum_and_the_full_adapter_contract_before_loading_sources(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exit-payload-');
        $this->assertIsString($path);
        $payload = [
            'adapter_version' => StockSpecificExitPortfolioSimulationService::ADAPTER_VERSION,
            'research_only' => true,
            'contract' => [
                'signal_adapter_only' => true,
                'portfolio_calculator' => 'App\\Services\\ServingPortfolioCalculator::calculate',
                'portfolio_calculator_modified' => false,
                'exit_models_entry_veto' => false,
                'no_global_strategy_replacement' => true,
                'exit_policy_enum' => [
                    StockSpecificExitPortfolioSimulationService::STOCK_SPECIFIC_FINAL_EXIT,
                    StockSpecificExitPortfolioSimulationService::FIXED_HORIZON_20T,
                ],
            ],
        ];
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
        config()->set('aktienki.portfolio_exit_backtest.payload_path', $path);
        config()->set('aktienki.portfolio_exit_backtest.payload_sha256', hash_file('sha256', $path));

        try {
            $service = new StockSpecificExitPortfolioSimulationService(new ServingPortfolioCalculator);
            $verify = \Closure::bind(fn (): array => $this->verifiedPayload(), $service, $service);
            [$loaded, $sha256] = $verify();

            $this->assertSame($payload, $loaded);
            $this->assertSame(hash_file('sha256', $path), $sha256);

            config()->set('aktienki.portfolio_exit_backtest.payload_sha256', str_repeat('0', 64));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Prüfsumme');
            $verify();
        } finally {
            @unlink($path);
        }
    }

    /** @return array<string, mixed> */
    private function source(): array
    {
        return [
            'configuration_key' => 'research:stock_specific_final_exit:TEST.DE',
            'release_id' => 'exit-research',
            'symbol' => 'TEST.DE',
            'instrument_id' => 1,
            'strategy_id' => 1,
            'strategy_name' => 'stock_specific_final_exit',
            'strategy_run_id' => 'frozen-run',
            'strategy' => 'stock_specific_final_exit',
            'priority' => 10,
            'horizon_days' => 20,
            'variant' => 'stock_specific_final_exit',
            'variant_label' => 'stock_specific_final_exit',
            'model_name' => 'per-stock selected exit',
            'quality_label' => 'research-only',
            'source_metrics' => [
                'trades' => 1,
                'hit_rate' => 1.0,
                'profit_factor' => 999999.0,
                'average_net_trade' => 0.1,
                'cumulative_return' => 0.1,
                'max_drawdown' => 0.0,
            ],
            'stored_transaction_cost' => 0.0,
            'trades' => [[
                'serving_strategy_trade_id' => 1,
                'entry_signal_date' => '2026-01-01',
                'entry_date' => '2026-01-02',
                'exit_signal_date' => '2026-01-10',
                'exit_date' => '2026-01-11',
                'entry_price' => 100.0,
                'exit_price' => 110.0,
                'entry_tcn_score' => 0.5,
                'holding_sessions' => 7,
                'exit_reason' => 'STOCK_SPECIFIC_SELECTED_EXIT',
                'transaction_cost' => 0.0,
                'net_return' => 0.1,
            ]],
        ];
    }
}
