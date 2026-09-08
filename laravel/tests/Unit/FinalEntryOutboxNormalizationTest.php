<?php

namespace Tests\Unit;

use App\Services\FinalEntryCanonicalizer;
use App\Services\FinalEntryShadowWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class FinalEntryOutboxNormalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.serving', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::purge('serving');
        Schema::connection('serving')->create(
            'serving_prediction_batch_outbox_events',
            function (Blueprint $table): void {
                $table->unsignedBigInteger('sequence_no');
                $table->string('stream_key');
                $table->unsignedBigInteger('instrument_id');
                $table->unsignedSmallInteger('horizon');
                $table->string('variant');
                $table->unsignedBigInteger('prediction_id');
                $table->text('prediction_snapshot');
                $table->char('payload_sha256', 64);
                $table->char('expected_scope_manifest_sha256', 64);
            },
        );
    }

    protected function tearDown(): void
    {
        DB::purge('serving');
        parent::tearDown();
    }

    public function test_all_frozen_enabled_scopes_are_retained_even_when_not_selected(): void
    {
        [$batch, $predictions] = $this->fixture();
        foreach ($predictions as $prediction) {
            DB::connection('serving')
                ->table('serving_prediction_batch_outbox_events')
                ->insert([
                    'sequence_no' => 1,
                    'stream_key' => 'stock-final-entry',
                    'instrument_id' => $prediction['instrument_id'],
                    'horizon' => $prediction['horizon'],
                    'variant' => $prediction['variant'],
                    'prediction_id' => $prediction['prediction_id'],
                    'prediction_snapshot' => json_encode(
                        $prediction,
                        JSON_THROW_ON_ERROR,
                    ),
                    'payload_sha256' => str_repeat('a', 64),
                    'expected_scope_manifest_sha256' => str_repeat('b', 64),
                ]);
        }

        $frozen = $this->invokeFrozenOutboxBatch($batch);

        self::assertCount(2, $frozen['rows']);
        self::assertFalse($frozen['rows'][1]['selected_for_prediction']);
        self::assertSame('basic', $frozen['rows'][1]['model_quality_class']);
        self::assertFalse($frozen['rows'][1]['quality_gate_passed']);
        self::assertSame([10, 20], $frozen['rows'][1]['quality_horizons']);
        self::assertSame(2, $frozen['coverage']['count']);
        self::assertSame(1, $frozen['coverage']['instrument_count']);
    }

    public function test_event_scope_diverging_from_frozen_parent_manifest_fails_closed(): void
    {
        [$batch, $predictions] = $this->fixture();
        $predictions[0]['scope']['prediction_enabled'] = false;
        $batch['prediction_manifest'] = $predictions;
        foreach ($predictions as $prediction) {
            DB::connection('serving')
                ->table('serving_prediction_batch_outbox_events')
                ->insert([
                    'sequence_no' => 1,
                    'stream_key' => 'stock-final-entry',
                    'instrument_id' => $prediction['instrument_id'],
                    'horizon' => $prediction['horizon'],
                    'variant' => $prediction['variant'],
                    'prediction_id' => $prediction['prediction_id'],
                    'prediction_snapshot' => json_encode($prediction, JSON_THROW_ON_ERROR),
                    'payload_sha256' => str_repeat('a', 64),
                    'expected_scope_manifest_sha256' => str_repeat('b', 64),
                ]);
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ENTRY_SHADOW_PREDICTION_SCOPE_NOT_FROZEN');

        $this->invokeFrozenOutboxBatch($batch);
    }

    /** @return array{0:array<string,mixed>,1:list<array<string,mixed>>} */
    private function fixture(): array
    {
        $releaseId = '20000000-0000-4000-8000-000000000001';
        $scope = fn (int $horizon, bool $selected, string $quality): array => [
            'instrument_id' => 501,
            'provider_symbol' => 'PUM.DE',
            'symbol' => 'PUM',
            'isin' => null,
            'exchange' => 'XETR',
            'country_code' => 'DE',
            'sector_code' => 'CONS',
            'instrument_type' => 'stock',
            'release_id' => $releaseId,
            'horizon' => $horizon,
            'variant' => 'standard',
            'selected_for_prediction' => $selected,
            'prediction_status' => 'eligible',
            'prediction_enabled' => true,
            'entry_policy' => ['minimum_confidence' => 0.6],
            'performance' => ['trades' => 12, 'profit_factor' => 1.4],
            'model_quality_class' => strtolower($quality),
            'model_quality_label' => $quality,
            'quality_gate_passed' => false,
            'release_content_sha256' => str_repeat('c', 64),
        ];
        $scopes = [
            $scope(10, true, 'Solid'),
            $scope(20, false, 'Basic'),
        ];
        $predictions = [];
        foreach ($scopes as $index => $expectedScope) {
            $predictions[] = [
                'prediction_id' => $index + 1,
                'batch_id' => '10000000-0000-4000-8000-000000000001',
                'instrument_id' => 501,
                'release_id' => $releaseId,
                'as_of' => '2026-09-07T16:00:00.000000Z',
                'horizon' => $expectedScope['horizon'],
                'variant' => 'standard',
                'expected_return' => $index === 0 ? 0.03 : -0.01,
                'target_price' => 25.0,
                'calibrated_score' => '3+',
                'risk_score' => 2,
                'signal' => $index === 0 ? 'BUY' : 'HOLD',
                'confidence' => 0.74,
                'compact_context' => ['last_price_eur' => 23.5],
                'created_at' => '2026-09-07T16:04:00.000000Z',
                'instrument' => [
                    'instrument_id' => 501,
                    'symbol' => 'PUM',
                    'provider_symbol' => 'PUM.DE',
                    'training_provider_symbol' => 'PUM.DE',
                    'isin' => null,
                    'exchange' => 'XETR',
                    'country_code' => 'DE',
                    'sector_code' => 'CONS',
                    'instrument_type' => 'stock',
                ],
                'scope' => array_intersect_key($expectedScope, array_flip([
                    'selected_for_prediction', 'prediction_status',
                    'prediction_enabled', 'entry_policy', 'performance',
                    'model_quality_class', 'model_quality_label',
                    'quality_gate_passed',
                ])),
                'release_content_sha256' => str_repeat('c', 64),
            ];
        }

        return [[
            'sequence_no' => 1,
            'batch_id' => '10000000-0000-4000-8000-000000000001',
            'calculation_date' => '2026-09-07',
            'pipeline_version' => 'fixed-horizon-inference-v2-oos-calibrated',
            'finished_at' => '2026-09-07T16:05:00.000000Z',
            'published_at' => '2026-09-07T16:06:00.000000Z',
            'expected_count' => 2,
            'completed_count' => 2,
            'failed_count' => 0,
            'stored_prediction_count' => 2,
            'expected_scope_count' => 2,
            'expected_stock_scope_count' => 2,
            'expected_stock_instrument_count' => 1,
            'error_summary' => [],
            'prediction_manifest' => $predictions,
            'expected_scope_manifest' => $scopes,
            'release_manifest' => [[
                'release_id' => $releaseId,
                'instrument_id' => 501,
                'content_sha256' => str_repeat('c', 64),
                'pipeline_version' => 'training-v1',
                'source_commit' => str_repeat('d', 40),
                'dataset_cutoff' => '2026-09-05T00:00:00.000000Z',
                'input_fingerprint' => str_repeat('e', 64),
                'compact_metrics_sha256' => str_repeat('f', 64),
                'artifact_manifest_sha256' => str_repeat('0', 64),
            ]],
            'expected_scope_manifest_sha256' => str_repeat('b', 64),
            'payload_sha256' => str_repeat('a', 64),
        ], $predictions];
    }

    /** @param array<string,mixed> $batch @return array<string,mixed> */
    private function invokeFrozenOutboxBatch(array $batch): array
    {
        $reflection = new \ReflectionClass(FinalEntryShadowWriter::class);
        $writer = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('canonicalizer')->setValue(
            $writer,
            new FinalEntryCanonicalizer,
        );

        return $reflection->getMethod('frozenOutboxBatch')->invoke($writer, $batch);
    }
}
