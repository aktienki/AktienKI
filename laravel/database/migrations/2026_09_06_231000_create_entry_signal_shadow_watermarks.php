<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_signal_shadow_watermarks', function (Blueprint $table): void {
            $table->string('worker_key', 64)->primary();
            $table->string('evaluator_version', 64);
            $table->timestampTz('cutover_at', 6);
            $table->string('source_contract_version', 64);
            $table->string('source_stream_key', 64);
            $table->unsignedBigInteger('last_sequence_no')->default(0);
            $table->uuid('last_batch_id')->nullable();
            $table->timestampTz('last_batch_completed_at', 6)->nullable();
            $table->date('last_calculation_date')->nullable();
            $table->char('last_payload_sha256', 64)->nullable();
            $table->unsignedBigInteger('processed_batches')->default(0);
            $table->jsonb('last_summary')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz(6);

            $table->foreign(['evaluator_version', 'cutover_at'])
                ->references(['evaluator_version', 'cutover_at'])
                ->on('entry_signal_runtime_config');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE entry_signal_shadow_watermarks
            ADD CONSTRAINT entry_signal_shadow_watermark_key_chk
                CHECK (worker_key = 'final-entry-shadow-v1'),
            ADD CONSTRAINT entry_signal_shadow_watermark_source_chk
                CHECK (
                    source_contract_version = 'serving-prediction-terminal-v1'
                    AND source_stream_key = 'stock-final-entry'
                ),
            ADD CONSTRAINT entry_signal_shadow_watermark_cursor_chk
                CHECK (
                    (
                        last_sequence_no = 0
                        AND last_batch_id IS NULL
                        AND last_batch_completed_at IS NULL
                        AND last_calculation_date IS NULL
                        AND last_payload_sha256 IS NULL
                        AND processed_batches = 0
                    )
                    OR (
                        last_sequence_no > 0
                        AND last_batch_id IS NOT NULL
                        AND last_batch_completed_at IS NOT NULL
                        AND last_calculation_date IS NOT NULL
                        AND last_payload_sha256 ~ '^[0-9a-f]{64}$'
                        AND processed_batches > 0
                        AND processed_batches = last_sequence_no
                    )
                ),
            ADD CONSTRAINT entry_signal_shadow_watermark_summary_chk
                CHECK (jsonb_typeof(last_summary) = 'object')
        SQL);

        Schema::create('entry_signal_shadow_processed_batches', function (Blueprint $table): void {
            $table->unsignedBigInteger('sequence_no')->primary();
            $table->string('contract_version', 64);
            $table->string('stream_key', 64);
            $table->uuid('batch_id')->unique();
            $table->date('calculation_date');
            $table->string('pipeline_version', 128);
            $table->string('status', 16);
            $table->timestampTz('source_finished_at', 6);
            $table->timestampTz('source_published_at', 6);
            $table->unsignedInteger('expected_count');
            $table->unsignedInteger('completed_count');
            $table->unsignedInteger('failed_count');
            $table->unsignedInteger('stored_prediction_count');
            $table->unsignedInteger('expected_scope_count');
            $table->unsignedInteger('expected_stock_scope_count');
            $table->unsignedInteger('expected_stock_instrument_count');
            $table->jsonb('error_summary');
            $table->char('header_sha256', 64);
            $table->char('prediction_manifest_sha256', 64);
            $table->char('expected_scope_manifest_sha256', 64);
            $table->char('release_manifest_sha256', 64);
            $table->char('request_sha256', 64);
            $table->char('payload_sha256', 64);
            $table->jsonb('processing_summary');
            $table->timestampTz('processed_at', 6);

            $table->unique(
                ['sequence_no', 'batch_id', 'payload_sha256'],
                'entry_shadow_batch_source_identity_uq',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE entry_signal_shadow_processed_batches
            ADD CONSTRAINT entry_shadow_batch_source_contract_chk
                CHECK (
                    sequence_no > 0
                    AND contract_version = 'serving-prediction-terminal-v1'
                    AND stream_key = 'stock-final-entry'
                    AND status = 'complete'
                ),
            ADD CONSTRAINT entry_shadow_batch_complete_chk
                CHECK (
                    expected_count > 0
                    AND failed_count = 0
                    AND completed_count = expected_count
                    AND stored_prediction_count = completed_count
                    AND expected_scope_count = expected_count
                    AND expected_stock_scope_count = expected_scope_count
                    AND expected_stock_instrument_count > 0
                    AND jsonb_typeof(error_summary) = 'array'
                    AND jsonb_array_length(error_summary) = 0
                ),
            ADD CONSTRAINT entry_shadow_batch_hashes_chk
                CHECK (
                    header_sha256 ~ '^[0-9a-f]{64}$'
                    AND prediction_manifest_sha256 ~ '^[0-9a-f]{64}$'
                    AND expected_scope_manifest_sha256 ~ '^[0-9a-f]{64}$'
                    AND release_manifest_sha256 ~ '^[0-9a-f]{64}$'
                    AND request_sha256 ~ '^[0-9a-f]{64}$'
                    AND payload_sha256 ~ '^[0-9a-f]{64}$'
                ),
            ADD CONSTRAINT entry_shadow_batch_summary_chk
                CHECK (jsonb_typeof(processing_summary) = 'object')
        SQL);

        Schema::create('entry_signal_shadow_session_events', function (Blueprint $table): void {
            $table->char('source_event_key', 64)->primary();
            $table->foreignId('instrument_id')->constrained();
            $table->unsignedBigInteger('source_instrument_id');
            $table->unsignedBigInteger('source_sequence_no');
            $table->uuid('batch_id');
            $table->char('outbox_payload_sha256', 64);
            $table->date('market_date');
            $table->timestampTz('source_as_of', 6);
            $table->char('mapping_sha256', 64);
            $table->char('scope_manifest_sha256', 64);
            $table->char('payload_sha256', 64);
            $table->char('evidence_sha256', 64);
            $table->jsonb('evidence');
            $table->timestampTz('materialized_at', 6);

            $table->foreign(
                ['source_sequence_no', 'batch_id', 'outbox_payload_sha256'],
                'entry_shadow_session_source_identity_fk',
            )
                ->references(['sequence_no', 'batch_id', 'payload_sha256'])
                ->on('entry_signal_shadow_processed_batches')
                ->deferrable()
                ->initiallyImmediate();
            $table->unique(
                ['instrument_id', 'market_date'],
                'entry_shadow_session_instrument_date_uq',
            );
            $table->index(
                ['instrument_id', 'source_as_of'],
                'entry_shadow_session_instrument_asof_idx',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE entry_signal_shadow_session_events
            ADD CONSTRAINT entry_shadow_session_hashes_chk
                CHECK (
                    source_event_key ~ '^[0-9a-f]{64}$'
                    AND mapping_sha256 ~ '^[0-9a-f]{64}$'
                    AND scope_manifest_sha256 ~ '^[0-9a-f]{64}$'
                    AND outbox_payload_sha256 ~ '^[0-9a-f]{64}$'
                    AND payload_sha256 ~ '^[0-9a-f]{64}$'
                    AND evidence_sha256 ~ '^[0-9a-f]{64}$'
                    AND jsonb_typeof(evidence) = 'object'
                    AND evidence->>'source_event_key' = source_event_key
                    AND evidence->>'payload_sha256' = payload_sha256
                    AND evidence->>'mapping_sha256' = mapping_sha256
                    AND evidence->>'scope_manifest_sha256' = scope_manifest_sha256
                    AND NULLIF(evidence->>'outbox_sequence_no', '')::bigint =
                        source_sequence_no
                    AND evidence->>'outbox_payload_sha256' = outbox_payload_sha256
                    AND NULLIF(evidence->>'batch_id', '')::uuid = batch_id
                    AND NULLIF(evidence->>'serving_instrument_id', '')::bigint =
                        source_instrument_id
                    AND NULLIF(evidence->>'market_date', '')::date = market_date
                    AND NULLIF(evidence->>'as_of', '')::timestamptz = source_as_of
                    AND evidence_sha256 = encode(
                        sha256(convert_to(evidence::text, 'UTF8')),
                        'hex'
                    )
                )
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION reject_entry_signal_shadow_ledger_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'entry signal shadow ledger rows are immutable';
            END;
            $$
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER entry_shadow_processed_batches_immutable
            BEFORE UPDATE OR DELETE ON entry_signal_shadow_processed_batches
            FOR EACH ROW EXECUTE FUNCTION reject_entry_signal_shadow_ledger_mutation()
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER entry_shadow_session_events_immutable
            BEFORE UPDATE OR DELETE ON entry_signal_shadow_session_events
            FOR EACH ROW EXECUTE FUNCTION reject_entry_signal_shadow_ledger_mutation()
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_signal_shadow_session_events');
        Schema::dropIfExists('entry_signal_shadow_processed_batches');
        DB::statement('DROP FUNCTION IF EXISTS reject_entry_signal_shadow_ledger_mutation()');
        Schema::dropIfExists('entry_signal_shadow_watermarks');
    }
};
