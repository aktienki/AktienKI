<?php

namespace Tests\Unit;

use App\Models\Portfolio;
use App\Services\ServingPortfolioCalculator;
use App\Services\ServingPortfolioSimulationService;
use App\Services\StockSpecificExitPortfolioSimulationService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class ServingPortfolioSimulationServiceTest extends TestCase
{
    #[Test]
    public function it_resolves_only_the_requested_model_variant(): void
    {
        $source = $this->resolve('pure_tcn', collect([
            $this->row(1, 'standard-run', 'standard-tcn-confirmed-fixed-horizon-oos-v3'),
            $this->row(2, 'pure-run', 'pure-tcn-calibrated-fixed-horizon-oos-v3'),
        ]));

        $this->assertSame('pure-run', $source['strategy_run_id']);
        $this->assertSame('pure_tcn', $source['source_variant']);
        $this->assertSame('exact', $source['source_match']);
        $this->assertSame(
            ServingPortfolioSimulationService::RAW_ENTRY_POLICY,
            $source['trades'][0]['entry_policy'],
        );
    }

    #[Test]
    public function it_does_not_fall_back_to_a_different_model_variant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('keine exakt passenden Serving-Trades');

        $this->resolve('pure_tcn', collect([
            $this->row(1, 'standard-run', 'standard-tcn-confirmed-fixed-horizon-oos-v3'),
        ]));
    }

    #[Test]
    public function it_routes_only_explicit_serving_model_configurations(): void
    {
        $service = new ServingPortfolioSimulationService(new ServingPortfolioCalculator);
        $legacy = $this->assignment(['signal' => 'BUY', 'profit_factor_min' => 1.2]);
        $invalidExplicit = $this->assignment([
            'serving_model_configurations' => [['source' => 'legacy_filter']],
        ]);
        $explicit = $this->assignment([
            'serving_model_configurations' => [[
                'source' => 'serving_model_overview',
                'symbol' => 'TEST.DE',
            ]],
        ]);

        $this->assertFalse($service->hasServingConfigurations(collect([$legacy])));
        $this->assertFalse($service->hasServingConfigurations(collect([$invalidExplicit])));
        $this->assertSame([], $this->configurationsFor($legacy));
        $this->assertTrue($service->hasServingConfigurations(collect([$explicit])));
        $this->assertTrue($service->allAssignmentsUseServingConfigurations(collect([$explicit])));
        $this->assertFalse($service->allAssignmentsUseServingConfigurations(collect([$explicit, $legacy])));
    }

    #[Test]
    public function it_labels_the_historical_generic_result_as_raw_and_research_only(): void
    {
        $result = $this->markRawResearchOnly(['source_type' => 'untrusted']);

        $this->assertSame(ServingPortfolioSimulationService::SOURCE_TYPE, $result['source_type']);
        $this->assertSame(ServingPortfolioSimulationService::RAW_ENTRY_POLICY, $result['entry_policy']);
        $this->assertSame(
            ServingPortfolioSimulationService::RAW_ENTRY_PROVENANCE_SCHEMA,
            $result['entry_provenance_schema'],
        );
        $this->assertTrue($result['research_only']);
    }

    #[Test]
    public function it_does_not_fall_back_when_published_metrics_do_not_match(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('keine exakt passenden Serving-Trades');

        $this->resolve('pure_tcn', collect([
            $this->row(1, 'pure-run', 'pure-tcn-calibrated-fixed-horizon-oos-v3', [
                'net_return' => .05,
            ]),
        ]));
    }

    #[Test]
    public function it_fails_closed_when_more_than_one_exact_trade_set_matches(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mehrere exakt passende Serving-Trade-Sätze');

        $this->resolve('pure_tcn', collect([
            $this->row(1, 'pure-run-a', 'pure-tcn-calibrated-fixed-horizon-oos-v3'),
            $this->row(2, 'pure-run-b', 'pure-tcn-calibrated-fixed-horizon-oos-v3'),
        ]));
    }

    #[Test]
    public function it_uses_an_explicit_strategy_run_binding_to_disambiguate_exact_sets(): void
    {
        $source = $this->resolve('pure_tcn', collect([
            $this->row(1, 'pure-run-a', 'pure-tcn-calibrated-fixed-horizon-oos-v3'),
            $this->row(2, 'pure-run-b', 'pure-tcn-calibrated-fixed-horizon-oos-v3'),
        ]), ['strategy_run_id' => 'pure-run-b']);

        $this->assertSame('pure-run-b', $source['strategy_run_id']);
    }

    #[Test]
    public function it_applies_the_saved_percent_median_threshold_fail_closed(): void
    {
        $this->assertTrue($this->passesMedianThreshold([], []));
        $this->assertTrue($this->passesMedianThreshold(
            ['median_return_min' => 9.79],
            ['median_net_trade' => .098],
        ));
        $this->assertFalse($this->passesMedianThreshold(
            ['median_return_min' => 9.81],
            ['median_net_trade' => .098],
        ));
        $this->assertFalse($this->passesMedianThreshold(['median_return_min' => 1.0], []));
        $this->assertFalse($this->passesMedianThreshold(
            ['median_return_min' => 1.0],
            ['median_net_trade' => INF],
        ));
    }

    #[Test]
    public function it_rejects_the_final_buy_source_when_the_saved_median_threshold_is_not_met(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Medianrendite nicht die Mindestschwelle');

        $this->resolve('pure_tcn', collect([
            $this->row(1, 'pure-run', 'pure-tcn-calibrated-fixed-horizon-oos-v3'),
        ]), [], ['median_return_min' => 9.81]);
    }

    #[Test]
    public function it_rejects_a_configured_median_threshold_when_the_published_metric_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Medianrendite nicht die Mindestschwelle');

        $this->resolve('pure_tcn', collect([
            $this->row(1, 'pure-run', 'pure-tcn-calibrated-fixed-horizon-oos-v3'),
        ]), [
            'metrics' => [
                'trades' => 1,
                'hit_rate' => 1.0,
                'average_net_trade' => .098,
                'cumulative_return' => .098,
            ],
        ], ['median_return_min' => 1.0]);
    }

    #[Test]
    public function it_rejects_a_missing_entry_signal_before_persisting_anything(): void
    {
        $service = new ServingPortfolioSimulationService(new ServingPortfolioCalculator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('enthält kein explizites BUY-Einstiegssignal');

        $service->persist(new Portfolio, 1, [
            'trade_log' => [['entry_date' => '2026-01-01']],
        ]);
    }

    #[Test]
    public function it_revalidates_all_final_entry_proof_at_the_persistence_boundary(): void
    {
        $cases = [
            'status' => [
                static fn (array $result): array => array_replace_recursive($result, [
                    'trade_log' => [0 => ['entry_filter_status' => 'FILTERED']],
                ]),
                'keinen persistierbaren finalen Postfilter-BUY',
            ],
            'row ledger membership' => [
                static fn (array $result): array => array_replace_recursive($result, [
                    'trade_log' => [0 => ['entry_postfilter_row_sha256' => str_repeat('7', 64)]],
                ]),
                'nicht im kanonischen BUY-Ereignisledger',
            ],
            'source bundle lineage' => [
                static fn (array $result): array => array_replace_recursive($result, [
                    'trade_log' => [0 => ['entry_source_bundle_manifest_sha256' => null]],
                ]),
                'nicht im kanonischen BUY-Ereignisledger',
            ],
            'assignment binding' => [
                static fn (array $result): array => array_replace_recursive($result, [
                    'trade_log' => [0 => ['assignment_filter_sha256' => null]],
                ]),
                'keinen persistierbaren finalen Postfilter-BUY',
            ],
        ];

        foreach ($cases as $description => [$mutate, $expectedMessage]) {
            try {
                (new ServingPortfolioSimulationService(new ServingPortfolioCalculator))->persist(
                    new Portfolio,
                    1,
                    $mutate($this->filteredResult()),
                );
                $this->fail("{$description} wurde an der Persistenzgrenze akzeptiert.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($expectedMessage, $exception->getMessage(), $description);
            }
        }
    }

    /** @return array<string, mixed> */
    private function resolve(
        string $variant,
        Collection $rows,
        array $configurationOverrides = [],
        array $filters = [],
    ): array {
        $service = new ServingPortfolioSimulationService(new ServingPortfolioCalculator);
        $configuration = array_replace([
            'key' => 'release:TEST.DE:20:'.$variant,
            'metrics' => [
                'trades' => 1,
                'hit_rate' => 1.0,
                'average_net_trade' => .098,
                'median_net_trade' => .098,
                'cumulative_return' => .098,
            ],
        ], $configurationOverrides);
        $resolver = \Closure::bind(
            fn (Collection $candidateRows, array $sourceConfiguration): array => $this->resolveSourceFromRows(
                (object) [
                    'saved_prediction_filter_id' => 10,
                    'strategy_name' => 'Test strategy',
                    'priority' => 1,
                    'filters' => $filters,
                ],
                $sourceConfiguration,
                'TEST.DE',
                'release',
                20,
                $variant,
                (object) ['id' => 99],
                $candidateRows,
            ),
            $service,
            $service,
        );

        return $resolver($rows, $configuration);
    }

    /** @param array<string, mixed> $overrides */
    private function row(int $id, string $runId, string $strategy, array $overrides = []): object
    {
        return (object) array_replace([
            'id' => $id,
            'strategy_run_id' => $runId,
            'strategy' => $strategy,
            'entry_signal' => 'BUY',
            'source_metadata' => json_encode(['release_id' => 'release']),
            'calculation_date' => '2026-09-05',
            'entry_date' => '2026-01-01',
            'entry_close_eur' => 100.0,
            'exit_date' => '2026-01-20',
            'exit_close_eur' => 110.0,
            'holding_days' => 20,
            'entry_tcn_score' => .5,
            'net_return' => .098,
            'transaction_cost' => .002,
            'exit_reason' => 'MAX_HOLDING_DAYS',
        ], $overrides);
    }

    /** @param array<string, mixed> $filters */
    private function assignment(array $filters): object
    {
        return (object) [
            'saved_prediction_filter_id' => 10,
            'strategy_name' => 'Test strategy',
            'priority' => 1,
            'filters' => $filters,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function configurationsFor(object $assignment): array
    {
        $service = new ServingPortfolioSimulationService(new ServingPortfolioCalculator);
        $resolver = \Closure::bind(
            fn (): array => $this->configurations($assignment),
            $service,
            $service,
        );

        return $resolver();
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function markRawResearchOnly(array $result): array
    {
        $service = new ServingPortfolioSimulationService(new ServingPortfolioCalculator);
        $resolver = \Closure::bind(
            fn (array $calculatorResult): array => $this->markRawResearchOnly($calculatorResult),
            $service,
            $service,
        );

        return $resolver($result);
    }

    /** @param array<string, mixed> $filters @param array<string, mixed> $metrics */
    private function passesMedianThreshold(array $filters, array $metrics): bool
    {
        $service = new ServingPortfolioSimulationService(new ServingPortfolioCalculator);
        $resolver = \Closure::bind(
            fn (): bool => $this->passesMedianReturnThreshold($filters, $metrics),
            $service,
            $service,
        );

        return $resolver();
    }

    /** @return array<string, mixed> */
    private function filteredResult(): array
    {
        $evidence = [
            'entry_signal' => 'BUY',
            'entry_filter_status' => 'ACCEPTED',
            'entry_filters_passed' => true,
            'entry_raw_rising_event' => true,
            'entry_postfilter_manifest_sha256' => str_repeat('a', 64),
            'entry_postfilter_row_sha256' => str_repeat('b', 64),
            'entry_postfilter_cache_id' => StockSpecificExitPortfolioSimulationService::POSTFILTER_CACHE_ID,
            'entry_postfilter_implementation_sha256' => StockSpecificExitPortfolioSimulationService::POSTFILTER_IMPLEMENTATION_SHA256,
            'entry_source_expanded_manifest_sha256' => str_repeat('c', 64),
            'entry_postfilter_daily_sha256' => str_repeat('d', 64),
            'entry_postfilter_summary_sha256' => str_repeat('e', 64),
            'entry_source_bundle_manifest_sha256' => str_repeat('f', 64),
            'entry_result_inventory_sha256' => str_repeat('1', 64),
            'entry_external_input_inventory_sha256' => str_repeat('2', 64),
            'entry_source_adapter_used' => false,
            'entry_source_adapter_procedure_version' => null,
            'entry_not_native_exit_bundle' => false,
            'entry_research_only' => true,
        ];
        $symbols = StockSpecificExitPortfolioSimulationService::SYMBOLS;
        $event = [
            'symbol' => 'BEI.DE',
            'entry_signal_date' => '2026-01-01',
            ...$evidence,
        ];
        $body = [
            'schema' => StockSpecificExitPortfolioSimulationService::ENTRY_LEDGER_SCHEMA,
            'entry_policy' => StockSpecificExitPortfolioSimulationService::ENTRY_POLICY,
            'entry_event_policy' => 'accepted_raw_rising_event_only',
            'window' => ['start' => '2026-01-01', 'end' => '2026-01-31'],
            'symbols' => $symbols,
            'symbol_event_counts' => array_replace(array_fill_keys($symbols, 0), ['BEI.DE' => 1]),
            'events' => [$event],
        ];
        $service = new ServingPortfolioSimulationService(new ServingPortfolioCalculator);
        $canonical = \Closure::bind(
            fn (array $value): string => $this->canonicalSha256($value),
            $service,
            $service,
        );
        $ledger = [...$body, 'sha256' => $canonical($body)];
        $sourcePayloadSha = str_repeat('8', 64);

        return [
            'source_type' => StockSpecificExitPortfolioSimulationService::SOURCE_TYPE,
            'entry_policy' => StockSpecificExitPortfolioSimulationService::ENTRY_POLICY,
            'entry_provenance_schema' => StockSpecificExitPortfolioSimulationService::ENTRY_PROVENANCE_SCHEMA,
            'entry_candidate_ledger_sha256' => $ledger['sha256'],
            'entry_candidate_ledger' => $ledger,
            'source_payload_sha256' => $sourcePayloadSha,
            'exit_policy' => StockSpecificExitPortfolioSimulationService::STOCK_SPECIFIC_FINAL_EXIT,
            'trade_log' => [[
                'symbol' => 'BEI.DE',
                'entry_signal_date' => '2026-01-01',
                'entry_policy' => StockSpecificExitPortfolioSimulationService::ENTRY_POLICY,
                'entry_provenance_schema' => StockSpecificExitPortfolioSimulationService::ENTRY_PROVENANCE_SCHEMA,
                'entry_candidate_ledger_sha256' => $ledger['sha256'],
                'assignment_filter_id' => 10,
                'assignment_filter_sha256' => str_repeat('9', 64),
                'source_payload_sha256' => $sourcePayloadSha,
                'variant' => StockSpecificExitPortfolioSimulationService::STOCK_SPECIFIC_FINAL_EXIT,
                'exit_reason' => 'STOCK_SPECIFIC_SELECTED_EXIT',
                'holding_days' => 7,
                ...$evidence,
            ]],
        ];
    }
}
