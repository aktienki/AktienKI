<?php

namespace Tests\Unit;

use App\Services\FinalEntryCanonicalizer;
use App\Services\FinalEntrySessionEvidenceBuilder;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FinalEntrySessionEvidenceBuilderTest extends TestCase
{
    private FinalEntryCanonicalizer $canonicalizer;

    private FinalEntrySessionEvidenceBuilder $builder;

    protected function setUp(): void
    {
        $this->canonicalizer = new FinalEntryCanonicalizer;
        $this->builder = new FinalEntrySessionEvidenceBuilder($this->canonicalizer);
    }

    public function test_authoritative_scope_rows_build_stable_evidence_in_tuple_order(): void
    {
        $rows = [
            $this->row(2, 20, 'pure_tcn'),
            $this->row(1, 10, 'standard'),
        ];

        $first = $this->build($rows);
        $second = $this->build(array_reverse($rows));

        self::assertTrue($first['gap_free']);
        self::assertSame($first, $second);
        self::assertCount(1, $first['sessions']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $first['sessions'][0]['source_event_key'],
        );
        self::assertSame(
            '10000000-0000-4000-8000-000000000001',
            $first['verified_through_batch_id'],
        );
        self::assertSame(1, $first['verified_through_sequence_no']);
        self::assertSame('Europe/Berlin', $first['session_timezone']);
    }

    public function test_a_duplicate_horizon_variant_tuple_fails_closed(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SESSION_SOURCE_SCOPE_DUPLICATE');

        $this->build([
            $this->row(1, 20, 'standard'),
            $this->row(2, 20, 'standard'),
        ]);
    }

    public function test_unselected_enabled_scope_still_proves_an_active_session(): void
    {
        $row = $this->row(1, 20, 'standard');
        $row['selected_for_prediction'] = false;

        $snapshot = $this->build([$row]);

        self::assertTrue($snapshot['sessions'][0]['scope_active']);
    }

    public function test_gap_free_requires_an_explicit_contiguous_cursor_proof(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SESSION_EVIDENCE_COVERAGE_UNPROVEN');

        $this->builder->build(
            [$this->row(1, 20, 'standard')],
            $this->mapping(),
            new DateTimeImmutable('2026-09-06T00:00:00Z'),
            new DateTimeImmutable('2026-09-07T16:00:00Z'),
            new DateTimeImmutable('2026-09-07T16:10:00Z'),
            null,
            false,
        );
    }

    public function test_a_new_session_appends_without_rewriting_prior_evidence(): void
    {
        $first = $this->build([$this->row(1, 20, 'standard')]);
        $priorHash = $this->canonicalizer->sha256($first['sessions'][0]);
        $nextRow = $this->row(2, 20, 'standard');
        $nextRow['batch_id'] = '10000000-0000-4000-8000-000000000002';
        $nextRow['calculation_date'] = '2026-09-08';
        $nextRow['as_of'] = '2026-09-08 16:00:00+00:00';
        $nextRow['batch_finished_at'] = '2026-09-08 16:05:00+00:00';
        $nextRow['outbox_sequence_no'] = 2;
        $nextRow['outbox_payload_sha256'] = str_repeat('d', 64);

        $second = $this->builder->build(
            [$nextRow],
            $this->mapping(),
            new DateTimeImmutable('2026-09-06T00:00:00Z'),
            new DateTimeImmutable('2026-09-08T16:00:00Z'),
            new DateTimeImmutable('2026-09-08T16:10:00Z'),
            $first,
            true,
        );

        self::assertCount(2, $second['sessions']);
        self::assertSame(
            $priorHash,
            $this->canonicalizer->sha256($second['sessions'][0]),
        );
        self::assertSame('2026-09-08', $second['sessions'][1]['market_date']);
    }

    public function test_feed_window_can_advance_after_older_lifecycles_are_closed(): void
    {
        $first = $this->build([$this->row(1, 20, 'standard')]);
        $dayTwo = $this->row(2, 20, 'standard');
        $dayTwo['batch_id'] = '10000000-0000-4000-8000-000000000002';
        $dayTwo['calculation_date'] = '2026-09-08';
        $dayTwo['as_of'] = '2026-09-08 16:00:00+00:00';
        $dayTwo['batch_finished_at'] = '2026-09-08 16:05:00+00:00';
        $dayTwo['outbox_sequence_no'] = 2;
        $dayTwo['outbox_payload_sha256'] = str_repeat('d', 64);
        $second = $this->builder->build(
            [$dayTwo],
            $this->mapping(),
            new DateTimeImmutable('2026-09-06T00:00:00Z'),
            new DateTimeImmutable('2026-09-08T16:00:00Z'),
            new DateTimeImmutable('2026-09-08T16:10:00Z'),
            $first,
            true,
        );
        $dayThree = $this->row(3, 20, 'standard');
        $dayThree['batch_id'] = '10000000-0000-4000-8000-000000000003';
        $dayThree['calculation_date'] = '2026-09-09';
        $dayThree['as_of'] = '2026-09-09 16:00:00+00:00';
        $dayThree['batch_finished_at'] = '2026-09-09 16:05:00+00:00';
        $dayThree['outbox_sequence_no'] = 3;
        $dayThree['outbox_payload_sha256'] = str_repeat('e', 64);

        $third = $this->builder->build(
            [$dayThree],
            $this->mapping(),
            new DateTimeImmutable('2026-09-08T16:00:00Z'),
            new DateTimeImmutable('2026-09-09T16:00:00Z'),
            new DateTimeImmutable('2026-09-09T16:10:00Z'),
            $second,
            true,
        );

        self::assertSame(
            '2026-09-08 16:00:00.000000+00:00',
            $third['coverage_start_as_of'],
        );
        self::assertSame(
            ['2026-09-08', '2026-09-09'],
            array_column($third['sessions'], 'market_date'),
        );
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function build(array $rows): array
    {
        return $this->builder->build(
            $rows,
            $this->mapping(),
            new DateTimeImmutable('2026-09-06T00:00:00Z'),
            new DateTimeImmutable('2026-09-07T16:00:00Z'),
            new DateTimeImmutable('2026-09-07T16:10:00Z'),
            null,
            true,
        );
    }

    /** @return array<string,mixed> */
    private function mapping(): array
    {
        $mapping = [
            'version' => 'serving-main-instrument-map-v1',
            'method' => 'unique_provider_symbol_exchange',
            'local_instrument_id' => 11,
            'serving_instrument_id' => 501,
            'isin' => null,
            'provider_symbol' => 'PUM.DE',
            'serving_exchange' => 'XETR',
            'main_exchange_code' => 'XETR',
            'main_exchange_mic' => 'XETR',
            'session_timezone' => 'Europe/Berlin',
        ];
        $mapping['sha256'] = $this->canonicalizer->sha256($mapping);

        return $mapping;
    }

    /** @return array<string,mixed> */
    private function row(int $predictionId, int $horizon, string $variant): array
    {
        return [
            'prediction_id' => $predictionId,
            'batch_id' => '10000000-0000-4000-8000-000000000001',
            'instrument_id' => 501,
            'release_id' => '20000000-0000-4000-8000-000000000001',
            'release_content_sha256' => str_repeat('a', 64),
            'scope_manifest_sha256' => str_repeat('b', 64),
            'outbox_sequence_no' => 1,
            'outbox_payload_sha256' => str_repeat('c', 64),
            'as_of' => '2026-09-07 16:00:00+00:00',
            'horizon' => $horizon,
            'variant' => $variant,
            'signal' => 'BUY',
            'expected_return' => '0.030000',
            'target_price' => '25.000000',
            'calibrated_score' => '2+',
            'risk_score' => 2,
            'confidence' => '0.740000',
            'compact_context' => ['last_price_eur' => 23.5],
            'batch_status' => 'complete',
            'batch_finished_at' => '2026-09-07 16:05:00+00:00',
            'calculation_date' => '2026-09-07',
            'pipeline_version' => 'pipeline-next-v1',
            'selected_for_prediction' => true,
            'prediction_enabled' => true,
            'prediction_status' => 'eligible',
            'source_release_bound' => true,
        ];
    }
}
