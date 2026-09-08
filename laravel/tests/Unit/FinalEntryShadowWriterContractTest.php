<?php

namespace Tests\Unit;

use App\Services\FinalEntryShadowWriter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FinalEntryShadowWriterContractTest extends TestCase
{
    private string $source;

    private \ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Services/FinalEntryShadowWriter.php',
        );
        $this->reflection = new \ReflectionClass(FinalEntryShadowWriter::class);
    }

    public function test_lifecycle_is_advanced_before_any_new_buy_is_evaluated(): void
    {
        $process = $this->methodSource('processBatch', 'frozenOutboxBatch');

        self::assertStringContainsString('advanceActiveLifecycles(', $process);
        self::assertStringContainsString('$this->decisions->evaluate(', $process);
        self::assertLessThan(
            strpos($process, '$this->decisions->evaluate('),
            strpos($process, 'advanceActiveLifecycles('),
        );
    }

    public function test_every_user_profile_and_each_owned_saved_filter_are_contexts(): void
    {
        $process = $this->methodSource('processBatch', 'frozenOutboxBatch');

        self::assertStringContainsString("'context_type' => 'user_profile'", $process);
        self::assertStringContainsString("'context_type' => 'saved_filter'", $process);
        self::assertStringContainsString(
            "savedPredictionFilterId: \$context['saved_prediction_filter_id']",
            $process,
        );
    }

    public function test_mapping_failure_aborts_the_batch_transaction(): void
    {
        $process = $this->methodSource('processBatch', 'frozenOutboxBatch');
        $catchStart = strpos($process, 'catch (LogicException $exception)');
        self::assertNotFalse($catchStart);
        $catch = substr($process, $catchStart, 650);

        self::assertStringContainsString('throw new LogicException(', $catch);
        self::assertStringNotContainsString('continue;', $catch);
    }

    public function test_selector_uses_only_gap_free_immutable_outbox_sequence(): void
    {
        $selector = $this->methodSource('nextOutboxEnvelope', 'assertOutboxEnvelope');

        self::assertStringContainsString('serving_prediction_batch_outbox as outbox', $selector);
        self::assertStringContainsString("->where('outbox.stream_key'", $selector);
        self::assertStringContainsString("->orderBy('outbox.sequence_no')", $selector);
        self::assertStringContainsString('$lastSequence + 1', $selector);
        self::assertStringContainsString('ENTRY_SHADOW_OUTBOX_SEQUENCE_GAP', $selector);
    }

    public function test_no_volatile_prediction_release_or_scope_table_is_a_source(): void
    {
        self::assertStringContainsString(
            "->table('serving_prediction_batch_outbox_events')",
            $this->source,
        );
        self::assertStringNotContainsString('serving_prediction_batches', $this->source);
        self::assertStringNotContainsString('serving_predictions as', $this->source);
        self::assertStringNotContainsString('serving_releases', $this->source);
        self::assertStringNotContainsString('serving_prediction_scopes', $this->source);
    }

    public function test_every_raw_buy_and_no_hold_is_evaluated_for_every_context(): void
    {
        $process = $this->methodSource('processBatch', 'frozenOutboxBatch');

        self::assertStringContainsString("=== 'BUY'", $process);
        self::assertStringContainsString('foreach ($buyRows as $prediction)', $process);
        self::assertStringContainsString('foreach ($contexts as $context)', $process);
        self::assertStringNotContainsString('break;', $process);
    }

    public function test_priority_is_deterministic_and_selection_is_only_a_tiebreaker(): void
    {
        $writer = $this->reflection->newInstanceWithoutConstructor();
        $method = $this->reflection->getMethod('predictionPriority');
        $base = [
            'id' => 1,
            'horizon' => 20,
            'variant' => 'standard',
            'expected_return' => 0.03,
            'quality_gate_passed' => false,
            'selected_for_prediction' => false,
            'model_quality_label' => 'Basic',
        ];
        $selected = $base;
        $selected['selected_for_prediction'] = true;

        self::assertGreaterThan(
            $method->invoke($writer, $selected),
            $method->invoke($writer, $base),
        );
        self::assertSame(4, $method->invoke($writer, $base)[1]);
    }

    public function test_idle_publication_window_allows_weekend_but_not_indefinitely(): void
    {
        $writer = $this->reflection->newInstanceWithoutConstructor();
        $method = $this->reflection->getMethod('idleRefreshWithinPublicationWindow');
        $snapshot = [
            'session_timezone' => 'Europe/Berlin',
            'sessions' => [['market_date' => '2026-09-04']], // Friday
        ];

        self::assertTrue($method->invoke(
            $writer,
            $snapshot,
            new DateTimeImmutable('2026-09-06T20:00:00+02:00'),
        ));
        self::assertFalse($method->invoke(
            $writer,
            $snapshot,
            new DateTimeImmutable('2026-09-08T00:01:00+02:00'),
        ));
    }

    public function test_migration_and_insert_share_the_complete_outbox_identity(): void
    {
        $migration = (string) file_get_contents(
            dirname(__DIR__, 2)
            .'/database/migrations/2026_09_06_231000_create_entry_signal_shadow_watermarks.php',
        );
        $materialize = $this->methodSource('materializeSessionEvents', 'priorFeedSnapshot');

        foreach ([
            "->unsignedBigInteger('last_sequence_no')",
            "->char('last_payload_sha256', 64)",
            "->unsignedBigInteger('source_sequence_no')",
            "->char('outbox_payload_sha256', 64)",
            'entry_shadow_session_source_identity_fk',
            'reject_entry_signal_shadow_ledger_mutation',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
        self::assertStringContainsString(
            'source_sequence_no, batch_id, outbox_payload_sha256',
            $materialize,
        );
        self::assertStringContainsString("\$session['outbox_sequence_no']", $materialize);
        self::assertStringContainsString("\$session['outbox_payload_sha256']", $materialize);
    }

    public function test_idle_refresh_is_watermark_bound_and_never_evaluates_decisions(): void
    {
        $idle = $this->methodSource('refreshIdleFeeds', 'snapshotMatchesWatermark');

        self::assertStringContainsString('intdiv($ttlSeconds, 2)', $idle);
        self::assertStringContainsString('snapshotMatchesWatermark(', $idle);
        self::assertStringContainsString('idleRefreshWithinPublicationWindow(', $idle);
        self::assertStringContainsString('recordReadyFeed(', $idle);
        self::assertStringContainsString('recordStaleFeed(', $idle);
        self::assertStringNotContainsString('$this->decisions->evaluate(', $idle);
        self::assertStringNotContainsString("DB::connection('serving')", $idle);
    }

    private function methodSource(string $method, string $nextMethod): string
    {
        $start = strpos($this->source, 'private function '.$method.'(');
        $end = strpos($this->source, 'private function '.$nextMethod.'(', $start + 1);
        self::assertNotFalse($start, $method);
        self::assertNotFalse($end, $nextMethod);

        return substr($this->source, $start, $end - $start);
    }
}
