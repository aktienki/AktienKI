<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_signal_runtime_config', function (Blueprint $table): void {
            $table->boolean('singleton')->primary()->default(true);
            $table->string('evaluator_version', 64)->unique();
            $table->timestampTz('cutover_at', 6);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->unique(
                ['evaluator_version', 'cutover_at'],
                'entry_signal_runtime_version_cutover_uq',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE entry_signal_runtime_config
            ADD CONSTRAINT entry_signal_runtime_singleton_chk CHECK (singleton = TRUE),
            ADD CONSTRAINT entry_signal_runtime_version_chk
                CHECK (evaluator_version = 'final-entry-v1')
        SQL);
        DB::statement(<<<'SQL'
            INSERT INTO entry_signal_runtime_config (
                singleton, evaluator_version, cutover_at, created_at
            ) VALUES (TRUE, 'final-entry-v1', clock_timestamp(), clock_timestamp())
        SQL);

        Schema::create('entry_signal_session_feed_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained();
            $table->string('provider_name', 48);
            $table->string('resolver_version', 64);
            $table->unsignedBigInteger('source_instrument_id');
            $table->char('mapping_sha256', 64);
            $table->string('status', 16)->default('PENDING');
            $table->jsonb('reason_codes')
                ->default(DB::raw("'[\"SESSION_FEED_PENDING\"]'::jsonb"));
            $table->timestampTz('heartbeat_at', 6)->nullable();
            $table->unsignedInteger('heartbeat_ttl_seconds')->default(900);
            $table->timestampTz('last_verified_source_as_of', 6)->nullable();
            $table->char('verification_payload_sha256', 64)->nullable();
            $table->jsonb('verification_snapshot')->nullable();
            $table->timestampsTz(6);

            $table->unique(
                ['instrument_id', 'provider_name'],
                'entry_session_feeds_instrument_provider_uq',
            );
            $table->index(
                ['status', 'heartbeat_at'],
                'entry_session_feeds_health_idx',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE entry_signal_session_feed_states
            ADD CONSTRAINT entry_session_feeds_provider_chk
                CHECK (provider_name IN ('serving_prediction', 'main_price_bar')),
            ADD CONSTRAINT entry_session_feeds_resolver_chk
                CHECK (resolver_version = 'trusted-session-evidence-v1'),
            ADD CONSTRAINT entry_session_feeds_status_chk
                CHECK (status IN ('PENDING', 'READY', 'STALE', 'ERROR')),
            ADD CONSTRAINT entry_session_feeds_hash_chk
                CHECK (
                    mapping_sha256 ~ '^[0-9a-f]{64}$'
                    AND (
                        (
                            verification_payload_sha256 IS NULL
                            AND verification_snapshot IS NULL
                        )
                        OR (
                            verification_payload_sha256 IS NOT NULL
                            AND verification_snapshot IS NOT NULL
                            AND verification_payload_sha256 ~ '^[0-9a-f]{64}$'
                            AND jsonb_typeof(verification_snapshot) = 'object'
                            AND verification_payload_sha256 = encode(
                                sha256(convert_to(verification_snapshot::text, 'UTF8')),
                                'hex'
                            )
                        )
                    )
                ),
            ADD CONSTRAINT entry_session_feeds_reasons_chk
                CHECK (
                    CASE
                        WHEN jsonb_typeof(reason_codes) IS DISTINCT FROM 'array' THEN FALSE
                        WHEN status = 'READY' THEN jsonb_array_length(reason_codes) = 0
                        ELSE jsonb_array_length(reason_codes) > 0
                    END
                ),
            ADD CONSTRAINT entry_session_feeds_heartbeat_chk
                CHECK (
                    heartbeat_ttl_seconds BETWEEN 30 AND 86400
                    AND (
                        last_verified_source_as_of IS NULL
                        OR (
                            heartbeat_at IS NOT NULL
                            AND last_verified_source_as_of <= heartbeat_at
                        )
                    )
                    AND (
                        (
                            status = 'READY'
                            AND heartbeat_at IS NOT NULL
                            AND last_verified_source_as_of IS NOT NULL
                            AND verification_payload_sha256 IS NOT NULL
                            AND verification_snapshot IS NOT NULL
                            AND jsonb_typeof(verification_snapshot) = 'object'
                        )
                        OR status <> 'READY'
                    )
                ),
            ADD CONSTRAINT entry_session_feeds_snapshot_binding_chk
                CHECK (
                    (
                        verification_snapshot IS NULL
                        OR (
                            heartbeat_at IS NOT NULL
                            AND last_verified_source_as_of IS NOT NULL
                            AND verification_snapshot->>'provider_name' IS NOT DISTINCT FROM
                                provider_name
                            AND verification_snapshot->>'resolver_version' IS NOT DISTINCT FROM
                                resolver_version
                            AND NULLIF(verification_snapshot->>'local_instrument_id', '') IS NOT NULL
                            AND (verification_snapshot->>'local_instrument_id')::bigint IS NOT DISTINCT FROM
                                instrument_id
                            AND NULLIF(verification_snapshot->>'source_instrument_id', '') IS NOT NULL
                            AND (verification_snapshot->>'source_instrument_id')::bigint IS NOT DISTINCT FROM
                                source_instrument_id
                            AND verification_snapshot->>'mapping_sha256' IS NOT DISTINCT FROM
                                mapping_sha256
                            AND NULLIF(verification_snapshot->>'heartbeat_at', '') IS NOT NULL
                            AND (verification_snapshot->>'heartbeat_at')::timestamptz IS NOT DISTINCT FROM
                                heartbeat_at
                            AND NULLIF(verification_snapshot->>'verified_through_as_of', '') IS NOT NULL
                            AND (verification_snapshot->>'verified_through_as_of')::timestamptz
                                IS NOT DISTINCT FROM last_verified_source_as_of
                            AND NULLIF(verification_snapshot->>'coverage_start_as_of', '') IS NOT NULL
                            AND (verification_snapshot->>'coverage_start_as_of')::timestamptz <=
                                last_verified_source_as_of
                            AND jsonb_typeof(verification_snapshot->'sessions') IS NOT DISTINCT FROM
                                'array'
                            AND COALESCE((verification_snapshot->>'gap_free')::boolean, FALSE)
                        )
                    )
                    AND (
                        provider_name <> 'main_price_bar'
                        OR source_instrument_id = instrument_id
                    )
                )
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION guard_entry_signal_session_feed_state()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                authoritative_timezone text;
                supplied_count integer;
                distinct_market_dates integer;
                distinct_event_keys integer;
                distinct_provider_ids integer;
                chronology_valid boolean;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'entry signal session feed state cannot be deleted directly';
                END IF;

                IF TG_OP = 'UPDATE' AND (
                    NEW.id,
                    NEW.instrument_id,
                    NEW.provider_name,
                    NEW.resolver_version,
                    NEW.source_instrument_id,
                    NEW.mapping_sha256,
                    NEW.created_at
                ) IS DISTINCT FROM (
                    OLD.id,
                    OLD.instrument_id,
                    OLD.provider_name,
                    OLD.resolver_version,
                    OLD.source_instrument_id,
                    OLD.mapping_sha256,
                    OLD.created_at
                ) THEN
                    RAISE EXCEPTION 'entry signal session feed identity is immutable';
                END IF;

                IF TG_OP = 'UPDATE' AND (
                    (
                        OLD.heartbeat_at IS NOT NULL
                        AND (
                            NEW.heartbeat_at IS NULL
                            OR NEW.heartbeat_at < OLD.heartbeat_at
                        )
                    )
                    OR (
                        OLD.last_verified_source_as_of IS NOT NULL
                        AND (
                            NEW.last_verified_source_as_of IS NULL
                            OR NEW.last_verified_source_as_of <
                                OLD.last_verified_source_as_of
                        )
                    )
                ) THEN
                    RAISE EXCEPTION 'entry signal session feed cannot move backwards';
                END IF;

                IF NEW.verification_snapshot IS NOT NULL THEN
                    IF jsonb_typeof(NEW.verification_snapshot) IS DISTINCT FROM 'object'
                       OR jsonb_typeof(NEW.verification_snapshot->'sessions') IS DISTINCT FROM
                            'array'
                    THEN
                        RAISE EXCEPTION 'invalid entry signal session feed snapshot';
                    END IF;

                    SELECT exchange.timezone
                    INTO STRICT authoritative_timezone
                    FROM instruments AS instrument
                    JOIN exchanges AS exchange
                      ON exchange.id = instrument.exchange_id
                    WHERE instrument.id = NEW.instrument_id;

                    WITH parsed_sessions AS (
                        SELECT session.value AS evidence,
                               session.value->>'source_system' AS source_system,
                               (session.value->>'market_date')::date AS market_date,
                               (session.value->>'as_of')::timestamptz AS as_of,
                               session.value->>'source_event_key' AS source_event_key,
                               CASE session.value->>'source_system'
                                   WHEN 'serving_prediction_batch'
                                       THEN session.value->>'batch_id'
                                   WHEN 'main_price_bar'
                                       THEN session.value->>'bar_id'
                                   ELSE NULL
                               END AS provider_id,
                               CASE
                                   WHEN NEW.provider_name = 'serving_prediction'
                                        AND session.value->>'source_system' =
                                            'serving_prediction_batch'
                                   THEN
                                       NULLIF(session.value->>'batch_id', '') IS NOT NULL
                                       AND NULLIF(session.value->>'release_id', '') IS NOT NULL
                                       AND session.value->>'batch_status' = 'complete'
                                       AND COALESCE(
                                           (session.value->>'scope_active')::boolean,
                                           FALSE
                                       )
                                       AND NULLIF(
                                           session.value->>'serving_instrument_id',
                                           ''
                                       ) IS NOT NULL
                                       AND (session.value->>'serving_instrument_id')::bigint =
                                           NEW.source_instrument_id
                                   WHEN NEW.provider_name = 'main_price_bar'
                                        AND session.value->>'source_system' = 'main_price_bar'
                                   THEN
                                       NULLIF(session.value->>'local_instrument_id', '') IS NOT NULL
                                       AND (session.value->>'local_instrument_id')::bigint =
                                           NEW.instrument_id
                                       AND session.value->>'interval' = '1d'
                                       AND COALESCE(
                                           (session.value->>'ohlc_valid')::boolean,
                                           FALSE
                                       )
                                       AND NULLIF(session.value->>'bar_id', '') IS NOT NULL
                                   ELSE FALSE
                               END AS provider_valid
                        FROM jsonb_array_elements(
                            NEW.verification_snapshot->'sessions'
                        ) AS session(value)
                    ), sequenced_sessions AS (
                        SELECT parsed.*,
                               LAG(as_of) OVER (
                                   ORDER BY market_date, as_of, source_event_key
                               ) AS previous_as_of
                        FROM parsed_sessions AS parsed
                    )
                    SELECT COUNT(*),
                           COUNT(DISTINCT market_date),
                           COUNT(DISTINCT source_event_key),
                           COUNT(DISTINCT source_system || ':' || provider_id),
                           CASE
                               WHEN COUNT(*) = 0 THEN TRUE
                               ELSE BOOL_AND(COALESCE(
                                   provider_valid
                                   AND evidence->>'provider_name' = NEW.provider_name
                                   AND evidence->>'mapping_sha256' = NEW.mapping_sha256
                                   AND source_event_key ~ '^[0-9a-f]{64}$'
                                   AND evidence->>'payload_sha256' ~ '^[0-9a-f]{64}$'
                                   AND as_of <= NEW.last_verified_source_as_of
                                   AND as_of >= (
                                       NEW.verification_snapshot->>'coverage_start_as_of'
                                   )::timestamptz
                                   AND market_date =
                                       (as_of AT TIME ZONE authoritative_timezone)::date
                                   AND (
                                       previous_as_of IS NULL
                                       OR as_of > previous_as_of
                                   ),
                                   FALSE
                               ))
                           END
                    INTO supplied_count,
                         distinct_market_dates,
                         distinct_event_keys,
                         distinct_provider_ids,
                         chronology_valid
                    FROM sequenced_sessions;

                    IF supplied_count <> distinct_market_dates
                       OR supplied_count <> distinct_event_keys
                       OR supplied_count <> distinct_provider_ids
                       OR NOT chronology_valid
                    THEN
                        RAISE EXCEPTION 'invalid, mixed, or duplicate session feed evidence';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER entry_signal_session_feed_state_guard
            BEFORE INSERT OR UPDATE OR DELETE ON entry_signal_session_feed_states
            FOR EACH ROW
            EXECUTE FUNCTION guard_entry_signal_session_feed_state()
        SQL);

        Schema::create('entry_signal_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context_type', 20);
            $table->string('context_key', 80);
            // Deliberately no FK: immutable audit remains valid if a saved strategy is deleted.
            $table->unsignedBigInteger('saved_prediction_filter_id')->nullable();
            $table->unsignedBigInteger('saved_prediction_filter_id_snapshot')->nullable();
            $table->foreignId('instrument_id')->constrained();
            $table->string('source_system', 16)->default('serving');
            // Diagnostic only. The stable identity is source_event_key.
            $table->unsignedBigInteger('source_prediction_id');
            $table->char('source_event_key', 64);
            $table->char('source_payload_sha256', 64);
            $table->uuid('source_batch_id')->nullable();
            $table->uuid('source_release_id')->nullable();
            $table->unsignedBigInteger('source_instrument_id')->nullable();
            $table->timestampTz('source_as_of', 6);
            $table->timestampTz('source_batch_completed_at', 6);
            $table->date('source_market_date');
            $table->unsignedSmallInteger('forecast_horizon_sessions');
            $table->string('source_variant', 32)->nullable();
            $table->string('raw_signal', 16);
            $table->string('model_quality_class', 32)->nullable();
            $table->boolean('filters_passed')->default(false);
            $table->string('decision_signal', 16);
            $table->string('decision_status', 32);
            $table->jsonb('reason_codes')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('source_snapshot');
            $table->jsonb('filter_snapshot');
            $table->char('filter_sha256', 64);
            $table->string('evaluator_version', 64);
            $table->timestampTz('cutover_at', 6);
            $table->timestampTz('evaluated_at', 6);
            $table->timestampsTz(6);

            $table->unique(
                ['user_id', 'context_key', 'source_system', 'source_event_key'],
                'entry_decisions_context_event_unique',
            );
            $table->unique(
                ['id', 'user_id', 'context_key', 'instrument_id', 'decision_status'],
                'entry_decisions_lifecycle_identity_uq',
            );
            $table->index(
                ['user_id', 'context_key', 'instrument_id', 'source_as_of'],
                'entry_decisions_context_instrument_idx',
            );
            $table->index(
                ['source_system', 'source_event_key'],
                'entry_decisions_source_event_idx',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE entry_signal_decisions
            ADD CONSTRAINT entry_decisions_runtime_config_fk
                FOREIGN KEY (evaluator_version, cutover_at)
                REFERENCES entry_signal_runtime_config (evaluator_version, cutover_at),
            ADD CONSTRAINT entry_decisions_context_type_chk
                CHECK (context_type IN ('user_profile', 'saved_filter')),
            ADD CONSTRAINT entry_decisions_context_filter_chk
                CHECK (
                    (
                        context_type = 'user_profile'
                        AND context_key = 'user'
                        AND saved_prediction_filter_id IS NULL
                        AND saved_prediction_filter_id_snapshot IS NULL
                    )
                    OR
                    (
                        context_type = 'saved_filter'
                        AND saved_prediction_filter_id_snapshot IS NOT NULL
                        AND context_key = 'strategy:' || saved_prediction_filter_id_snapshot::text
                        AND (
                            saved_prediction_filter_id IS NULL
                            OR saved_prediction_filter_id = saved_prediction_filter_id_snapshot
                        )
                    )
                ),
            ADD CONSTRAINT entry_decisions_source_system_chk
                CHECK (source_system IN ('serving', 'legacy')),
            ADD CONSTRAINT entry_decisions_serving_identity_chk
                CHECK (
                    source_system <> 'serving'
                    OR (
                        source_batch_id IS NOT NULL
                        AND source_release_id IS NOT NULL
                        AND source_instrument_id IS NOT NULL
                        AND NULLIF(BTRIM(source_variant), '') IS NOT NULL
                    )
                ),
            ADD CONSTRAINT entry_decisions_horizon_chk
                CHECK (forecast_horizon_sessions > 0),
            ADD CONSTRAINT entry_decisions_cutover_chk
                CHECK (
                    source_as_of >= cutover_at
                    AND source_as_of <= source_batch_completed_at
                    AND source_batch_completed_at >= cutover_at
                    AND evaluated_at >= source_batch_completed_at
                ),
            ADD CONSTRAINT entry_decisions_raw_signal_chk
                CHECK (raw_signal IN ('BUY', 'HOLD', 'WATCH', 'WAIT', 'SELL', 'UNKNOWN')),
            ADD CONSTRAINT entry_decisions_signal_chk
                CHECK (decision_signal IN ('BUY', 'HOLD', 'WATCH', 'WAIT', 'SELL')),
            ADD CONSTRAINT entry_decisions_status_chk
                CHECK (decision_status IN (
                    'PASSTHROUGH', 'FILTERED', 'ACCEPTED',
                    'SUPPRESSED_ACTIVE', 'STALE_EVENT', 'ERROR'
                )),
            ADD CONSTRAINT entry_decisions_status_result_chk
                CHECK (
                    (
                        decision_status = 'ACCEPTED'
                        AND decision_signal = 'BUY'
                        AND raw_signal = 'BUY'
                        AND filters_passed = TRUE
                    )
                    OR
                    (
                        decision_status = 'PASSTHROUGH'
                        AND raw_signal <> 'BUY'
                        AND decision_signal = raw_signal
                        AND filters_passed = FALSE
                    )
                    OR
                    (
                        decision_status = 'FILTERED'
                        AND raw_signal = 'BUY'
                        AND filters_passed = FALSE
                        AND decision_signal IN ('WATCH', 'HOLD')
                    )
                    OR
                    (
                        decision_status = 'SUPPRESSED_ACTIVE'
                        AND raw_signal = 'BUY'
                        AND filters_passed = TRUE
                        AND decision_signal IN ('WATCH', 'HOLD')
                    )
                    OR
                    (
                        decision_status IN ('STALE_EVENT', 'ERROR')
                        AND filters_passed = FALSE
                        AND decision_signal IN ('WATCH', 'HOLD')
                    )
                ),
            ADD CONSTRAINT entry_decisions_buy_iff_accepted_chk
                CHECK ((decision_signal = 'BUY') = (decision_status = 'ACCEPTED')),
            ADD CONSTRAINT entry_decisions_hashes_chk
                CHECK (
                    source_event_key ~ '^[0-9a-f]{64}$'
                    AND source_payload_sha256 ~ '^[0-9a-f]{64}$'
                    AND filter_sha256 ~ '^[0-9a-f]{64}$'
                ),
            ADD CONSTRAINT entry_decisions_reasons_array_chk
                CHECK (jsonb_typeof(reason_codes) = 'array'),
            ADD CONSTRAINT entry_decisions_rejection_reason_chk
                CHECK (
                    decision_status IN ('ACCEPTED', 'PASSTHROUGH')
                    OR jsonb_array_length(reason_codes) > 0
                )
        SQL);

        Schema::create('entry_signal_lifecycles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entry_signal_decision_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context_key', 80);
            $table->foreignId('instrument_id')->constrained();
            $table->string('decision_status', 32)->default('ACCEPTED');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('activated_at', 6);
            // Prospective expiry is unknown until the H-th observed 1d session.
            $table->date('expiry_market_date')->nullable();
            $table->timestampTz('expires_at', 6)->nullable();
            $table->unsignedBigInteger('session_feed_state_id');
            $table->string('session_feed_provider', 48);
            $table->string('session_feed_resolver_version', 64);
            $table->char('session_mapping_sha256', 64);
            $table->string('session_timezone', 64);
            $table->unsignedSmallInteger('observed_sessions')->default(0);
            $table->date('last_observed_market_date')->nullable();
            $table->timestampTz('last_observed_as_of', 6)->nullable();
            $table->char('session_evidence_sha256', 64)
                ->default('4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945');
            $table->jsonb('session_evidence')->default(DB::raw("'[]'::jsonb"));
            $table->timestampTz('session_clock_updated_at', 6);
            $table->string('session_feed_status', 16)->default('PENDING');
            $table->jsonb('session_feed_reason_codes')
                ->default(DB::raw("'[\"SESSION_FEED_PENDING\"]'::jsonb"));
            // Snapshot references only: registry rows may later be retired/deleted.
            $table->unsignedBigInteger('configured_exit_profile_id')->nullable();
            $table->string('configured_exit_profile_signature', 128)->nullable();
            $table->string('configured_exit_policy_name', 80)->nullable();
            $table->string('configured_exit_policy_version', 64)->nullable();
            $table->jsonb('configured_exit_snapshot')->nullable();
            // Entry execution consumption only; UI/email reads never consume.
            $table->timestampTz('entry_consumed_at', 6)->nullable();
            $table->string('entry_consumer_type', 32)->nullable();
            $table->jsonb('entry_consumer_reference')->nullable();
            $table->timestampTz('closed_at', 6)->nullable();
            $table->timestampTz('closed_effective_as_of', 6)->nullable();
            $table->string('close_reason', 32)->nullable();
            $table->string('exit_source_system', 16)->nullable();
            $table->char('exit_source_event_key', 64)->nullable();
            $table->unsignedBigInteger('exit_observation_id')->nullable();
            $table->timestampTz('exit_as_of', 6)->nullable();
            $table->string('exit_policy_name', 80)->nullable();
            $table->string('exit_policy_version', 64)->nullable();
            $table->char('exit_payload_sha256', 64)->nullable();
            $table->jsonb('exit_snapshot')->nullable();
            $table->timestampsTz(6);

            $table->index(
                ['status', 'expires_at'],
                'entry_lifecycles_status_expiry_idx',
            );
            $table->index(
                ['user_id', 'context_key', 'instrument_id', 'closed_effective_as_of'],
                'entry_lifecycles_context_instrument_idx',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE entry_signal_lifecycles
            ADD CONSTRAINT entry_lifecycles_feed_state_fk
                FOREIGN KEY (session_feed_state_id)
                REFERENCES entry_signal_session_feed_states (id),
            ADD CONSTRAINT entry_lifecycles_decision_identity_fk
            FOREIGN KEY (
                entry_signal_decision_id,
                user_id,
                context_key,
                instrument_id,
                decision_status
            )
            REFERENCES entry_signal_decisions (
                id,
                user_id,
                context_key,
                instrument_id,
                decision_status
            )
            ON DELETE CASCADE
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX entry_lifecycles_one_active_idx
            ON entry_signal_lifecycles (user_id, context_key, instrument_id)
            WHERE status = 'ACTIVE'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE entry_signal_lifecycles
            ADD CONSTRAINT entry_lifecycles_decision_status_chk
                CHECK (decision_status = 'ACCEPTED'),
            ADD CONSTRAINT entry_lifecycles_status_chk
                CHECK (status IN ('ACTIVE', 'CLOSED_EXIT', 'EXPIRED')),
            ADD CONSTRAINT entry_lifecycles_expiry_chk
                CHECK (expires_at IS NULL OR expires_at > activated_at),
            ADD CONSTRAINT entry_lifecycles_session_clock_chk
                CHECK (
                    jsonb_typeof(session_evidence) = 'array'
                    AND session_feed_status IN ('PENDING', 'READY', 'STALE', 'ERROR')
                    AND jsonb_typeof(session_feed_reason_codes) = 'array'
                    AND (
                        (
                            session_feed_status = 'READY'
                            AND jsonb_array_length(session_feed_reason_codes) = 0
                        )
                        OR (
                            session_feed_status <> 'READY'
                            AND jsonb_array_length(session_feed_reason_codes) > 0
                        )
                    )
                    AND session_mapping_sha256 ~ '^[0-9a-f]{64}$'
                    AND session_evidence_sha256 ~ '^[0-9a-f]{64}$'
                    AND session_clock_updated_at >= activated_at
                    AND (
                        last_observed_as_of IS NULL
                        OR last_observed_as_of <= session_clock_updated_at
                    )
                    AND (
                        (
                            observed_sessions = 0
                            AND last_observed_market_date IS NULL
                            AND last_observed_as_of IS NULL
                        )
                        OR (
                            observed_sessions > 0
                            AND last_observed_market_date IS NOT NULL
                            AND last_observed_as_of IS NOT NULL
                        )
                    )
                ),
            ADD CONSTRAINT entry_lifecycles_configured_exit_chk
                CHECK (
                    (
                        configured_exit_policy_name IS NULL
                        AND configured_exit_policy_version IS NULL
                        AND configured_exit_profile_id IS NULL
                        AND configured_exit_profile_signature IS NULL
                        AND configured_exit_snapshot IS NULL
                    )
                    OR
                    (
                        configured_exit_policy_name IS NOT NULL
                        AND configured_exit_policy_version IS NOT NULL
                        AND configured_exit_snapshot IS NOT NULL
                        AND (
                            (
                                configured_exit_profile_id IS NULL
                                AND configured_exit_profile_signature IS NULL
                            )
                            OR (
                                configured_exit_profile_id IS NOT NULL
                                AND NULLIF(BTRIM(configured_exit_profile_signature), '') IS NOT NULL
                            )
                        )
                    )
                ),
            ADD CONSTRAINT entry_lifecycles_entry_consumed_chk
                CHECK (
                    (
                        entry_consumed_at IS NULL
                        AND entry_consumer_type IS NULL
                        AND entry_consumer_reference IS NULL
                    )
                    OR
                    (
                        entry_consumed_at IS NOT NULL
                        AND entry_consumer_type IN ('AUTOMATION', 'MANUAL_PORTFOLIO')
                        AND entry_consumer_reference IS NOT NULL
                        AND jsonb_typeof(entry_consumer_reference) = 'object'
                        AND entry_consumed_at >= activated_at
                        AND (
                            expires_at IS NULL
                            OR entry_consumed_at < expires_at
                        )
                    )
                ),
            ADD CONSTRAINT entry_lifecycles_exit_source_chk
                CHECK (
                    exit_source_system IS NULL
                    OR exit_source_system IN ('serving', 'legacy', 'exit_profile')
                ),
            ADD CONSTRAINT entry_lifecycles_exit_hash_chk
                CHECK (
                    exit_source_event_key IS NULL
                    OR exit_source_event_key ~ '^[0-9a-f]{64}$'
                ),
            ADD CONSTRAINT entry_lifecycles_exit_payload_hash_chk
                CHECK (
                    exit_payload_sha256 IS NULL
                    OR exit_payload_sha256 ~ '^[0-9a-f]{64}$'
                ),
            ADD CONSTRAINT entry_lifecycles_terminal_chk
                CHECK (
                    (
                        status = 'ACTIVE'
                        AND expiry_market_date IS NULL
                        AND expires_at IS NULL
                        AND closed_at IS NULL
                        AND closed_effective_as_of IS NULL
                        AND close_reason IS NULL
                        AND exit_source_system IS NULL
                        AND exit_source_event_key IS NULL
                        AND exit_observation_id IS NULL
                        AND exit_as_of IS NULL
                        AND exit_policy_name IS NULL
                        AND exit_policy_version IS NULL
                        AND exit_payload_sha256 IS NULL
                        AND exit_snapshot IS NULL
                    )
                    OR
                    (
                        status = 'CLOSED_EXIT'
                        AND closed_at IS NOT NULL
                        AND closed_effective_as_of IS NOT NULL
                        AND close_reason = 'STOCK_SPECIFIC_EXIT'
                        AND exit_source_system IS NOT NULL
                        AND exit_source_event_key IS NOT NULL
                        AND exit_as_of IS NOT NULL
                        AND exit_policy_name IS NOT NULL
                        AND exit_policy_version IS NOT NULL
                        AND exit_payload_sha256 IS NOT NULL
                        AND exit_snapshot IS NOT NULL
                    )
                    OR
                    (
                        status = 'EXPIRED'
                        AND expiry_market_date IS NOT NULL
                        AND expires_at IS NOT NULL
                        AND closed_at IS NOT NULL
                        AND closed_effective_as_of IS NOT NULL
                        AND closed_effective_as_of = expires_at
                        AND close_reason = 'HORIZON_EXPIRED'
                        AND exit_source_system IS NULL
                        AND exit_source_event_key IS NULL
                        AND exit_observation_id IS NULL
                        AND exit_as_of IS NULL
                        AND exit_policy_name IS NULL
                        AND exit_policy_version IS NULL
                        AND exit_payload_sha256 IS NULL
                        AND exit_snapshot IS NULL
                    )
                )
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION reject_entry_signal_runtime_config_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'entry signal runtime cutover is immutable';
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER entry_signal_runtime_config_immutable
            BEFORE UPDATE OR DELETE ON entry_signal_runtime_config
            FOR EACH ROW
            EXECUTE FUNCTION reject_entry_signal_runtime_config_mutation()
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION record_entry_signal_session_feed_state(
                p_instrument_id bigint,
                p_provider_name varchar,
                p_resolver_version varchar,
                p_source_instrument_id bigint,
                p_mapping_sha256 char(64),
                p_status varchar,
                p_reason_codes jsonb,
                p_heartbeat_at timestamptz,
                p_heartbeat_ttl_seconds integer,
                p_last_verified_source_as_of timestamptz,
                p_verification_payload_sha256 char(64),
                p_verification_snapshot jsonb
            )
            RETURNS bigint
            LANGUAGE plpgsql
            AS $$
            DECLARE
                feed_id bigint;
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtext('entry-session-feed:' || p_instrument_id::text));

                IF p_provider_name IS NULL
                   OR p_provider_name NOT IN ('serving_prediction', 'main_price_bar')
                   OR p_resolver_version IS DISTINCT FROM 'trusted-session-evidence-v1'
                   OR p_source_instrument_id IS NULL
                   OR (
                        p_provider_name = 'main_price_bar'
                        AND p_source_instrument_id IS DISTINCT FROM p_instrument_id
                   )
                   OR p_mapping_sha256 IS NULL
                   OR p_mapping_sha256 !~ '^[0-9a-f]{64}$'
                   OR p_status IS NULL
                   OR p_status NOT IN ('PENDING', 'READY', 'STALE', 'ERROR')
                   OR jsonb_typeof(p_reason_codes) IS DISTINCT FROM 'array'
                   OR (
                      CASE
                        WHEN jsonb_typeof(p_reason_codes) IS DISTINCT FROM 'array' THEN TRUE
                        WHEN p_status = 'READY' THEN jsonb_array_length(p_reason_codes) <> 0
                        ELSE jsonb_array_length(p_reason_codes) = 0
                      END
                   )
                   OR p_heartbeat_ttl_seconds IS NULL
                   OR p_heartbeat_ttl_seconds NOT BETWEEN 30 AND 86400
                   OR (
                        p_heartbeat_at IS NOT NULL
                        AND p_heartbeat_at > clock_timestamp() + INTERVAL '5 minutes'
                   )
                   OR (
                        p_last_verified_source_as_of IS NOT NULL
                        AND (
                            p_heartbeat_at IS NULL
                            OR p_last_verified_source_as_of > p_heartbeat_at
                        )
                   )
                   OR (p_verification_payload_sha256 IS NULL) <>
                        (p_verification_snapshot IS NULL)
                   OR (
                      CASE
                        WHEN p_verification_snapshot IS NULL THEN FALSE
                        ELSE
                            p_verification_payload_sha256 !~ '^[0-9a-f]{64}$'
                            OR jsonb_typeof(p_verification_snapshot) IS DISTINCT FROM 'object'
                            OR p_verification_payload_sha256 IS DISTINCT FROM encode(
                                sha256(convert_to(p_verification_snapshot::text, 'UTF8')),
                                'hex'
                            )
                            OR p_verification_snapshot->>'provider_name' IS DISTINCT FROM
                                p_provider_name
                            OR p_verification_snapshot->>'resolver_version' IS DISTINCT FROM
                                p_resolver_version
                            OR NULLIF(p_verification_snapshot->>'local_instrument_id', '') IS NULL
                            OR (p_verification_snapshot->>'local_instrument_id')::bigint IS DISTINCT FROM
                                p_instrument_id
                            OR NULLIF(p_verification_snapshot->>'source_instrument_id', '') IS NULL
                            OR (p_verification_snapshot->>'source_instrument_id')::bigint IS DISTINCT FROM
                                p_source_instrument_id
                            OR p_verification_snapshot->>'mapping_sha256' IS DISTINCT FROM
                                p_mapping_sha256
                            OR NULLIF(p_verification_snapshot->>'heartbeat_at', '') IS NULL
                            OR (p_verification_snapshot->>'heartbeat_at')::timestamptz IS DISTINCT FROM
                                p_heartbeat_at
                            OR NULLIF(p_verification_snapshot->>'verified_through_as_of', '') IS NULL
                            OR (p_verification_snapshot->>'verified_through_as_of')::timestamptz
                                IS DISTINCT FROM p_last_verified_source_as_of
                            OR NULLIF(p_verification_snapshot->>'coverage_start_as_of', '') IS NULL
                            OR (p_verification_snapshot->>'coverage_start_as_of')::timestamptz >
                                p_last_verified_source_as_of
                            OR jsonb_typeof(p_verification_snapshot->'sessions') IS DISTINCT FROM
                                'array'
                            OR NOT COALESCE(
                                (p_verification_snapshot->>'gap_free')::boolean,
                                FALSE
                            )
                      END
                   )
                   OR (
                        p_status = 'READY'
                        AND (
                            p_heartbeat_at IS NULL
                            OR p_last_verified_source_as_of IS NULL
                            OR p_verification_payload_sha256 IS NULL
                            OR p_verification_snapshot IS NULL
                        )
                   )
                THEN
                    RAISE EXCEPTION 'invalid authoritative session feed state';
                END IF;

                INSERT INTO entry_signal_session_feed_states (
                    instrument_id,
                    provider_name,
                    resolver_version,
                    source_instrument_id,
                    mapping_sha256,
                    status,
                    reason_codes,
                    heartbeat_at,
                    heartbeat_ttl_seconds,
                    last_verified_source_as_of,
                    verification_payload_sha256,
                    verification_snapshot,
                    created_at,
                    updated_at
                ) VALUES (
                    p_instrument_id,
                    p_provider_name,
                    p_resolver_version,
                    p_source_instrument_id,
                    p_mapping_sha256,
                    p_status,
                    p_reason_codes,
                    p_heartbeat_at,
                    p_heartbeat_ttl_seconds,
                    p_last_verified_source_as_of,
                    p_verification_payload_sha256,
                    p_verification_snapshot,
                    clock_timestamp(),
                    clock_timestamp()
                )
                ON CONFLICT (instrument_id, provider_name)
                DO UPDATE SET
                    status = EXCLUDED.status,
                    reason_codes = EXCLUDED.reason_codes,
                    heartbeat_at = EXCLUDED.heartbeat_at,
                    heartbeat_ttl_seconds = EXCLUDED.heartbeat_ttl_seconds,
                    last_verified_source_as_of = EXCLUDED.last_verified_source_as_of,
                    verification_payload_sha256 = EXCLUDED.verification_payload_sha256,
                    verification_snapshot = EXCLUDED.verification_snapshot,
                    updated_at = clock_timestamp()
                WHERE entry_signal_session_feed_states.resolver_version = EXCLUDED.resolver_version
                  AND entry_signal_session_feed_states.source_instrument_id = EXCLUDED.source_instrument_id
                  AND entry_signal_session_feed_states.mapping_sha256 = EXCLUDED.mapping_sha256
                  AND (
                      entry_signal_session_feed_states.heartbeat_at IS NULL
                      OR EXCLUDED.heartbeat_at >= entry_signal_session_feed_states.heartbeat_at
                  )
                  AND (
                      entry_signal_session_feed_states.last_verified_source_as_of IS NULL
                      OR EXCLUDED.last_verified_source_as_of >=
                            entry_signal_session_feed_states.last_verified_source_as_of
                  )
                RETURNING id INTO feed_id;

                IF feed_id IS NULL THEN
                    RAISE EXCEPTION 'session feed identity changed or heartbeat moved backwards';
                END IF;

                RETURN feed_id;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION reject_entry_signal_decision_update()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND pg_trigger_depth() > 1 THEN
                    -- Preserve user-cascade/GDPR deletion while rejecting direct deletion.
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'entry_signal_decisions are immutable';
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER entry_signal_decisions_immutable
            BEFORE UPDATE OR DELETE ON entry_signal_decisions
            FOR EACH ROW
            EXECUTE FUNCTION reject_entry_signal_decision_update()
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION require_accepted_decision_lifecycle()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.decision_status = 'ACCEPTED'
                   AND NOT EXISTS (
                       SELECT 1
                       FROM entry_signal_lifecycles AS lifecycle
                       WHERE lifecycle.entry_signal_decision_id = NEW.id
                   )
                THEN
                    RAISE EXCEPTION 'accepted decision % requires a lifecycle', NEW.id;
                END IF;

                RETURN NULL;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER entry_decision_requires_lifecycle
            AFTER INSERT ON entry_signal_decisions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION require_accepted_decision_lifecycle()
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION preflight_entry_signal_activation(
                p_entry_signal_decision_id bigint,
                p_session_feed_state_id bigint,
                p_observed_at timestamptz
            )
            RETURNS jsonb
            LANGUAGE plpgsql
            AS $$
            DECLARE
                decision_instrument_id bigint;
                decision_source_instrument_id bigint;
                decision_as_of timestamptz;
                decision_source_market_date date;
                decision_horizon smallint;
                authoritative_timezone text;
                authoritative_feed entry_signal_session_feed_states%ROWTYPE;
                supplied_count integer;
                distinct_market_dates integer;
                distinct_event_keys integer;
                distinct_provider_ids integer;
                chronology_valid boolean;
                relevant_count integer;
                retained_count integer;
                retained_last_date date;
                retained_last_as_of timestamptz;
                retained_evidence jsonb;
            BEGIN
                SELECT decision.instrument_id,
                       decision.source_instrument_id,
                       decision.source_as_of,
                       decision.source_market_date,
                       decision.forecast_horizon_sessions,
                       exchange.timezone
                INTO STRICT decision_instrument_id,
                            decision_source_instrument_id,
                            decision_as_of,
                            decision_source_market_date,
                            decision_horizon,
                            authoritative_timezone
                FROM entry_signal_decisions AS decision
                JOIN instruments AS instrument
                  ON instrument.id = decision.instrument_id
                JOIN exchanges AS exchange
                  ON exchange.id = instrument.exchange_id
                WHERE decision.id = p_entry_signal_decision_id;

                SELECT feed.*
                INTO STRICT authoritative_feed
                FROM entry_signal_session_feed_states AS feed
                WHERE feed.id = p_session_feed_state_id
                  AND feed.instrument_id = decision_instrument_id
                FOR SHARE;

                IF p_observed_at IS NULL
                   OR p_observed_at < decision_as_of
                   OR p_observed_at > clock_timestamp() + INTERVAL '5 minutes'
                   OR authoritative_feed.status <> 'READY'
                   OR authoritative_feed.heartbeat_at IS NULL
                   OR authoritative_feed.heartbeat_at +
                        make_interval(secs => authoritative_feed.heartbeat_ttl_seconds)
                        <= clock_timestamp()
                   OR authoritative_feed.heartbeat_at > clock_timestamp() + INTERVAL '5 minutes'
                   OR authoritative_feed.resolver_version IS DISTINCT FROM
                        'trusted-session-evidence-v1'
                   OR authoritative_feed.verification_payload_sha256 IS NULL
                   OR authoritative_feed.verification_snapshot IS NULL
                   OR authoritative_feed.verification_payload_sha256 IS DISTINCT FROM encode(
                        sha256(convert_to(authoritative_feed.verification_snapshot::text, 'UTF8')),
                        'hex'
                   )
                   OR authoritative_feed.last_verified_source_as_of IS NULL
                   OR authoritative_feed.last_verified_source_as_of < decision_as_of
                   OR NULLIF(authoritative_feed.verification_snapshot->>'coverage_start_as_of', '')
                        IS NULL
                   OR (authoritative_feed.verification_snapshot->>'coverage_start_as_of')::timestamptz
                        > decision_as_of
                   OR jsonb_typeof(authoritative_feed.verification_snapshot->'sessions')
                        IS DISTINCT FROM 'array'
                   OR NOT COALESCE(
                        (authoritative_feed.verification_snapshot->>'gap_free')::boolean,
                        FALSE
                   )
                   OR NULLIF(BTRIM(authoritative_timezone), '') IS NULL
                   OR decision_source_market_date IS DISTINCT FROM
                        (decision_as_of AT TIME ZONE authoritative_timezone)::date
                   OR NOT (
                        (
                            authoritative_feed.provider_name = 'serving_prediction'
                            AND authoritative_feed.source_instrument_id IS NOT DISTINCT FROM
                                decision_source_instrument_id
                        )
                        OR (
                            authoritative_feed.provider_name = 'main_price_bar'
                            AND authoritative_feed.source_instrument_id IS NOT DISTINCT FROM
                                decision_instrument_id
                        )
                   )
                THEN
                    RAISE EXCEPTION 'authoritative session-feed preflight failed';
                END IF;

                WITH parsed_sessions AS (
                    SELECT session.value AS evidence,
                           session.value->>'source_system' AS source_system,
                           (session.value->>'market_date')::date AS market_date,
                           (session.value->>'as_of')::timestamptz AS as_of,
                           session.value->>'source_event_key' AS source_event_key,
                           CASE session.value->>'source_system'
                               WHEN 'serving_prediction_batch' THEN session.value->>'batch_id'
                               WHEN 'main_price_bar' THEN session.value->>'bar_id'
                               ELSE NULL
                           END AS provider_id,
                           CASE
                               WHEN authoritative_feed.provider_name = 'serving_prediction'
                                    AND session.value->>'source_system' =
                                        'serving_prediction_batch'
                               THEN
                                   NULLIF(session.value->>'batch_id', '') IS NOT NULL
                                   AND NULLIF(session.value->>'release_id', '') IS NOT NULL
                                   AND session.value->>'batch_status' = 'complete'
                                   AND COALESCE(
                                       (session.value->>'scope_active')::boolean,
                                       FALSE
                                   )
                                   AND NULLIF(
                                       session.value->>'serving_instrument_id',
                                       ''
                                   ) IS NOT NULL
                                   AND (session.value->>'serving_instrument_id')::bigint =
                                       decision_source_instrument_id
                               WHEN authoritative_feed.provider_name = 'main_price_bar'
                                    AND session.value->>'source_system' = 'main_price_bar'
                               THEN
                                   NULLIF(session.value->>'local_instrument_id', '') IS NOT NULL
                                   AND (session.value->>'local_instrument_id')::bigint =
                                       decision_instrument_id
                                   AND session.value->>'interval' = '1d'
                                   AND COALESCE(
                                       (session.value->>'ohlc_valid')::boolean,
                                       FALSE
                                   )
                                   AND NULLIF(session.value->>'bar_id', '') IS NOT NULL
                               ELSE FALSE
                           END AS provider_valid,
                           session.ordinality
                    FROM jsonb_array_elements(
                        authoritative_feed.verification_snapshot->'sessions'
                    ) WITH ORDINALITY AS session(value, ordinality)
                ), sequenced_sessions AS (
                    SELECT parsed.*,
                           LAG(as_of) OVER (
                               ORDER BY market_date, as_of, source_event_key
                           ) AS previous_as_of
                    FROM parsed_sessions AS parsed
                )
                SELECT COUNT(*),
                       COUNT(DISTINCT market_date),
                       COUNT(DISTINCT source_event_key),
                       COUNT(DISTINCT source_system || ':' || provider_id),
                       CASE
                           WHEN COUNT(*) = 0 THEN TRUE
                           ELSE BOOL_AND(COALESCE(
                               provider_valid
                               AND evidence->>'provider_name' =
                                   authoritative_feed.provider_name
                               AND evidence->>'mapping_sha256' =
                                   authoritative_feed.mapping_sha256
                               AND source_event_key ~ '^[0-9a-f]{64}$'
                               AND evidence->>'payload_sha256' ~ '^[0-9a-f]{64}$'
                               AND as_of <= authoritative_feed.last_verified_source_as_of
                               AND as_of >= (
                                   authoritative_feed.verification_snapshot->>
                                       'coverage_start_as_of'
                               )::timestamptz
                               AND market_date =
                                   (as_of AT TIME ZONE authoritative_timezone)::date
                               AND (
                                   previous_as_of IS NULL
                                   OR as_of > previous_as_of
                               ),
                               FALSE
                           ))
                       END
                INTO supplied_count,
                     distinct_market_dates,
                     distinct_event_keys,
                     distinct_provider_ids,
                     chronology_valid
                FROM sequenced_sessions;

                IF supplied_count <> distinct_market_dates
                   OR supplied_count <> distinct_event_keys
                   OR supplied_count <> distinct_provider_ids
                   OR NOT chronology_valid
                THEN
                    RAISE EXCEPTION 'invalid authoritative session-feed evidence';
                END IF;

                WITH relevant_sessions AS (
                    SELECT session.value AS evidence,
                           (session.value->>'market_date')::date AS market_date,
                           (session.value->>'as_of')::timestamptz AS as_of,
                           session.value->>'source_event_key' AS source_event_key
                    FROM jsonb_array_elements(
                        authoritative_feed.verification_snapshot->'sessions'
                    ) AS session(value)
                    WHERE (session.value->>'as_of')::timestamptz > decision_as_of
                      AND (session.value->>'as_of')::timestamptz <= p_observed_at
                      AND (session.value->>'market_date')::date > decision_source_market_date
                ), retained_sessions AS (
                    SELECT *
                    FROM relevant_sessions
                    ORDER BY market_date, as_of, source_event_key
                    LIMIT decision_horizon
                )
                SELECT (SELECT COUNT(*) FROM relevant_sessions),
                       COUNT(*)::integer,
                       MAX(market_date),
                       MAX(as_of),
                       COALESCE(
                           jsonb_agg(evidence ORDER BY market_date, as_of, source_event_key),
                           '[]'::jsonb
                       )
                INTO relevant_count,
                     retained_count,
                     retained_last_date,
                     retained_last_as_of,
                     retained_evidence
                FROM retained_sessions;

                RETURN jsonb_build_object(
                    'stale', relevant_count >= decision_horizon,
                    'horizon_sessions', decision_horizon,
                    'observed_sessions', retained_count,
                    'last_observed_market_date', retained_last_date,
                    'last_observed_as_of', retained_last_as_of,
                    'sessions', retained_evidence,
                    'sessions_sha256', encode(
                        sha256(convert_to(retained_evidence::text, 'UTF8')),
                        'hex'
                    ),
                    'feed_state_id', authoritative_feed.id,
                    'provider_name', authoritative_feed.provider_name,
                    'verification_payload_sha256',
                        authoritative_feed.verification_payload_sha256,
                    'heartbeat_at', authoritative_feed.heartbeat_at
                );
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION guard_entry_signal_lifecycle()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                decision_as_of timestamptz;
                decision_source_instrument_id bigint;
                decision_source_market_date date;
                decision_horizon smallint;
                authoritative_timezone text;
                authoritative_feed entry_signal_session_feed_states%ROWTYPE;
                activation_preflight jsonb;
            BEGIN
                SELECT decision.source_as_of,
                       decision.source_instrument_id,
                       decision.source_market_date,
                       decision.forecast_horizon_sessions,
                       exchange.timezone
                INTO STRICT decision_as_of,
                            decision_source_instrument_id,
                            decision_source_market_date,
                            decision_horizon,
                            authoritative_timezone
                FROM entry_signal_decisions AS decision
                JOIN instruments AS instrument
                  ON instrument.id = decision.instrument_id
                JOIN exchanges AS exchange
                  ON exchange.id = instrument.exchange_id
                WHERE decision.id = NEW.entry_signal_decision_id;

                IF jsonb_typeof(NEW.session_evidence) IS DISTINCT FROM 'array'
                   OR jsonb_array_length(NEW.session_evidence) <> NEW.observed_sessions
                   OR NEW.session_evidence_sha256 IS DISTINCT FROM encode(
                        sha256(convert_to(NEW.session_evidence::text, 'UTF8')),
                        'hex'
                   )
                   OR (
                        NEW.observed_sessions = 0
                        AND (
                            NEW.last_observed_market_date IS NOT NULL
                            OR NEW.last_observed_as_of IS NOT NULL
                        )
                   )
                   OR (
                        NEW.observed_sessions > 0
                        AND (
                            NEW.last_observed_market_date IS DISTINCT FROM
                                (NEW.session_evidence->(NEW.observed_sessions - 1)->>'market_date')::date
                            OR NEW.last_observed_as_of IS DISTINCT FROM
                                (NEW.session_evidence->(NEW.observed_sessions - 1)->>'as_of')::timestamptz
                        )
                   )
                THEN
                    RAISE EXCEPTION 'lifecycle session evidence is inconsistent';
                END IF;

                IF TG_OP = 'INSERT' THEN
                    IF NEW.status <> 'ACTIVE' THEN
                        RAISE EXCEPTION 'new entry lifecycle must start ACTIVE';
                    END IF;

                    -- Activation time and preflight cutoff are DB authoritative.
                    NEW.activated_at := clock_timestamp();

                    IF NEW.activated_at < decision_as_of
                       OR (
                           NEW.expires_at IS NOT NULL
                           AND NEW.expires_at <= decision_as_of
                       )
                    THEN
                        RAISE EXCEPTION 'invalid lifecycle timing for decision %',
                            NEW.entry_signal_decision_id;
                    END IF;

                    IF NULLIF(BTRIM(authoritative_timezone), '') IS NULL
                       OR NEW.session_timezone <> authoritative_timezone
                    THEN
                        RAISE EXCEPTION 'unverified session timezone for decision %',
                            NEW.entry_signal_decision_id;
                    END IF;

                    IF decision_source_market_date <>
                        (decision_as_of AT TIME ZONE authoritative_timezone)::date
                    THEN
                        RAISE EXCEPTION 'source market date does not match exchange timezone';
                    END IF;

                    SELECT feed.*
                    INTO STRICT authoritative_feed
                    FROM entry_signal_session_feed_states AS feed
                    WHERE feed.id = NEW.session_feed_state_id
                      AND feed.instrument_id = NEW.instrument_id
                    FOR SHARE;

                    IF authoritative_feed.status <> 'READY'
                       OR authoritative_feed.heartbeat_at IS NULL
                       OR authoritative_feed.heartbeat_at +
                            make_interval(secs => authoritative_feed.heartbeat_ttl_seconds)
                            <= clock_timestamp()
                       OR authoritative_feed.heartbeat_at > clock_timestamp() + INTERVAL '5 minutes'
                       OR NEW.session_feed_provider <> authoritative_feed.provider_name
                       OR NEW.session_feed_resolver_version <> authoritative_feed.resolver_version
                       OR NEW.session_mapping_sha256 <> authoritative_feed.mapping_sha256
                       OR authoritative_feed.verification_payload_sha256 IS NULL
                       OR authoritative_feed.verification_snapshot IS NULL
                       OR authoritative_feed.verification_payload_sha256 IS DISTINCT FROM encode(
                            sha256(convert_to(authoritative_feed.verification_snapshot::text, 'UTF8')),
                            'hex'
                       )
                       OR NOT (
                            (
                                authoritative_feed.provider_name = 'serving_prediction'
                                AND authoritative_feed.source_instrument_id IS NOT DISTINCT FROM
                                    decision_source_instrument_id
                            )
                            OR (
                                authoritative_feed.provider_name = 'main_price_bar'
                                AND authoritative_feed.source_instrument_id IS NOT DISTINCT FROM
                                    NEW.instrument_id
                            )
                       )
                    THEN
                        RAISE EXCEPTION 'session feed is not authoritatively READY';
                    END IF;

                    activation_preflight := preflight_entry_signal_activation(
                        NEW.entry_signal_decision_id,
                        NEW.session_feed_state_id,
                        NEW.activated_at
                    );

                    IF COALESCE((activation_preflight->>'stale')::boolean, TRUE)
                    THEN
                        RAISE EXCEPTION 'source event already reached its forecast horizon';
                    END IF;

                    NEW.observed_sessions :=
                        (activation_preflight->>'observed_sessions')::smallint;
                    NEW.last_observed_market_date := NULLIF(
                        activation_preflight->>'last_observed_market_date',
                        ''
                    )::date;
                    NEW.last_observed_as_of := NULLIF(
                        activation_preflight->>'last_observed_as_of',
                        ''
                    )::timestamptz;
                    NEW.session_evidence := activation_preflight->'sessions';
                    NEW.session_evidence_sha256 :=
                        activation_preflight->>'sessions_sha256';
                    NEW.session_clock_updated_at := NEW.activated_at;
                    NEW.session_feed_status := 'READY';
                    NEW.session_feed_reason_codes := '[]'::jsonb;
                    NEW.expiry_market_date := NULL;
                    NEW.expires_at := NULL;

                    IF EXISTS (
                        SELECT 1
                        FROM entry_signal_lifecycles AS prior
                        WHERE prior.user_id = NEW.user_id
                          AND prior.context_key = NEW.context_key
                          AND prior.instrument_id = NEW.instrument_id
                          AND prior.status IN ('CLOSED_EXIT', 'EXPIRED')
                          AND prior.closed_effective_as_of >= decision_as_of
                    )
                    THEN
                        RAISE EXCEPTION 'source event is not newer than terminal lifecycle';
                    END IF;

                    RETURN NEW;
                END IF;

                IF OLD.status <> 'ACTIVE' THEN
                    RAISE EXCEPTION 'terminal entry lifecycle cannot be changed';
                END IF;

                IF (
                    NEW.entry_signal_decision_id,
                    NEW.user_id,
                    NEW.context_key,
                    NEW.instrument_id,
                    NEW.decision_status,
                    NEW.activated_at,
                    NEW.session_feed_state_id,
                    NEW.session_feed_provider,
                    NEW.session_feed_resolver_version,
                    NEW.session_mapping_sha256,
                    NEW.session_timezone,
                    NEW.configured_exit_profile_id,
                    NEW.configured_exit_profile_signature,
                    NEW.configured_exit_policy_name,
                    NEW.configured_exit_policy_version,
                    NEW.configured_exit_snapshot
                ) IS DISTINCT FROM (
                    OLD.entry_signal_decision_id,
                    OLD.user_id,
                    OLD.context_key,
                    OLD.instrument_id,
                    OLD.decision_status,
                    OLD.activated_at,
                    OLD.session_feed_state_id,
                    OLD.session_feed_provider,
                    OLD.session_feed_resolver_version,
                    OLD.session_mapping_sha256,
                    OLD.session_timezone,
                    OLD.configured_exit_profile_id,
                    OLD.configured_exit_profile_signature,
                    OLD.configured_exit_policy_name,
                    OLD.configured_exit_policy_version,
                    OLD.configured_exit_snapshot
                )
                THEN
                    RAISE EXCEPTION 'entry lifecycle identity/configuration is immutable';
                END IF;

                IF NEW.observed_sessions < OLD.observed_sessions
                   OR (
                       OLD.last_observed_market_date IS NOT NULL
                       AND NEW.last_observed_market_date < OLD.last_observed_market_date
                   )
                   OR (
                       OLD.last_observed_as_of IS NOT NULL
                       AND NEW.last_observed_as_of < OLD.last_observed_as_of
                   )
                   OR NEW.session_clock_updated_at < OLD.session_clock_updated_at
                THEN
                    RAISE EXCEPTION 'entry lifecycle session clock cannot move backwards';
                END IF;

                IF NEW.status = 'ACTIVE'
                   AND NEW.observed_sessions >= decision_horizon
                THEN
                    RAISE EXCEPTION 'active lifecycle reached its forecast horizon';
                END IF;

                IF NEW.status = 'EXPIRED'
                   AND NEW.observed_sessions <> decision_horizon
                THEN
                    RAISE EXCEPTION 'lifecycle cannot expire before its forecast horizon';
                END IF;

                IF OLD.entry_consumed_at IS NOT NULL
                   AND (
                       NEW.entry_consumed_at,
                       NEW.entry_consumer_type,
                       NEW.entry_consumer_reference
                   ) IS DISTINCT FROM (
                       OLD.entry_consumed_at,
                       OLD.entry_consumer_type,
                       OLD.entry_consumer_reference
                   )
                THEN
                    RAISE EXCEPTION 'entry event was already consumed';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER entry_signal_lifecycle_guard
            BEFORE INSERT OR UPDATE ON entry_signal_lifecycles
            FOR EACH ROW
            EXECUTE FUNCTION guard_entry_signal_lifecycle()
        SQL);

        DB::statement(<<<'SQL'
            COMMENT ON COLUMN entry_signal_lifecycles.entry_consumed_at IS
            'Entry execution consumption only; UI and email reads never consume an event.'
        SQL);

        DB::statement(<<<'SQL'
            CREATE FUNCTION advance_entry_signal_lifecycle(
                p_user_id bigint,
                p_context_key varchar,
                p_instrument_id bigint,
                p_observed_at timestamptz,
                p_session_clock jsonb,
                p_exit jsonb DEFAULT NULL
            )
            RETURNS bigint
            LANGUAGE plpgsql
            AS $$
            DECLARE
                lifecycle_row entry_signal_lifecycles%ROWTYPE;
                decision_as_of timestamptz;
                decision_source_instrument_id bigint;
                decision_source_market_date date;
                decision_horizon smallint;
                candidate_exit_as_of timestamptz;
                horizon_reached_at timestamptz;
                horizon_market_date date;
                observed_count smallint := 0;
                observed_last_date date;
                observed_last_as_of timestamptz;
                observed_evidence jsonb := '[]'::jsonb;
                observed_evidence_sha256 char(64);
                feed_status varchar(16);
                feed_reasons jsonb;
                clock_verified_at timestamptz;
                supplied_count integer;
                distinct_market_dates integer;
                distinct_event_keys integer;
                distinct_provider_ids integer;
                chronology_valid boolean;
                authoritative_feed entry_signal_session_feed_states%ROWTYPE;
                authoritative_preflight jsonb;
                exit_is_valid boolean := FALSE;
            BEGIN
                PERFORM pg_advisory_xact_lock(
                    hashtext(p_user_id::text || ':' || p_context_key || ':' || p_instrument_id::text)
                );

                SELECT lifecycle.*
                INTO lifecycle_row
                FROM entry_signal_lifecycles AS lifecycle
                WHERE lifecycle.user_id = p_user_id
                  AND lifecycle.context_key = p_context_key
                  AND lifecycle.instrument_id = p_instrument_id
                  AND lifecycle.status = 'ACTIVE'
                FOR UPDATE;

                IF NOT FOUND THEN
                    RETURN NULL;
                END IF;

                SELECT source_as_of,
                       source_instrument_id,
                       source_market_date,
                       forecast_horizon_sessions
                INTO STRICT decision_as_of,
                            decision_source_instrument_id,
                            decision_source_market_date,
                            decision_horizon
                FROM entry_signal_decisions
                WHERE id = lifecycle_row.entry_signal_decision_id;

                IF p_observed_at IS NULL OR p_observed_at < decision_as_of THEN
                    RAISE EXCEPTION 'session observation precedes source prediction';
                END IF;

                IF p_observed_at > clock_timestamp() + INTERVAL '5 minutes' THEN
                    RAISE EXCEPTION 'session observation is in the future';
                END IF;

                observed_count := lifecycle_row.observed_sessions;
                observed_last_date := lifecycle_row.last_observed_market_date;
                observed_last_as_of := lifecycle_row.last_observed_as_of;
                observed_evidence := lifecycle_row.session_evidence;
                observed_evidence_sha256 := lifecycle_row.session_evidence_sha256;
                feed_status := lifecycle_row.session_feed_status;
                feed_reasons := lifecycle_row.session_feed_reason_codes;
                clock_verified_at := lifecycle_row.session_clock_updated_at;

                SELECT feed.*
                INTO STRICT authoritative_feed
                FROM entry_signal_session_feed_states AS feed
                WHERE feed.id = lifecycle_row.session_feed_state_id
                  AND feed.instrument_id = lifecycle_row.instrument_id
                  AND feed.provider_name = lifecycle_row.session_feed_provider
                  AND feed.resolver_version = lifecycle_row.session_feed_resolver_version
                  AND feed.mapping_sha256 = lifecycle_row.session_mapping_sha256
                  AND (
                      (
                          feed.provider_name = 'serving_prediction'
                          AND feed.source_instrument_id IS NOT DISTINCT FROM
                              decision_source_instrument_id
                      )
                      OR (
                          feed.provider_name = 'main_price_bar'
                          AND feed.source_instrument_id IS NOT DISTINCT FROM
                              lifecycle_row.instrument_id
                      )
                  )
                FOR SHARE;

                clock_verified_at := clock_timestamp();
                feed_status := authoritative_feed.status;
                feed_reasons := authoritative_feed.reason_codes;
                IF feed_status = 'READY' AND (
                    authoritative_feed.heartbeat_at IS NULL
                    OR authoritative_feed.heartbeat_at + make_interval(
                        secs => authoritative_feed.heartbeat_ttl_seconds
                    ) <= clock_timestamp()
                    OR authoritative_feed.heartbeat_at >
                        clock_timestamp() + INTERVAL '5 minutes'
                ) THEN
                    feed_status := 'STALE';
                    feed_reasons := '["SESSION_FEED_HEARTBEAT_EXPIRED"]'::jsonb;
                END IF;

                IF p_session_clock IS NOT NULL AND (
                    jsonb_typeof(p_session_clock) IS DISTINCT FROM 'object'
                    OR NULLIF(p_session_clock->>'feed_state_id', '') IS NULL
                    OR (p_session_clock->>'feed_state_id')::bigint IS DISTINCT FROM
                        authoritative_feed.id
                    OR p_session_clock->>'provider_name' IS DISTINCT FROM
                        authoritative_feed.provider_name
                    OR p_session_clock->>'resolver_version' IS DISTINCT FROM
                        authoritative_feed.resolver_version
                    OR NULLIF(p_session_clock->>'local_instrument_id', '') IS NULL
                    OR (p_session_clock->>'local_instrument_id')::bigint IS DISTINCT FROM
                        lifecycle_row.instrument_id
                    OR NULLIF(p_session_clock->>'source_instrument_id', '') IS NULL
                    OR (p_session_clock->>'source_instrument_id')::bigint IS DISTINCT FROM
                        authoritative_feed.source_instrument_id
                    OR p_session_clock->>'mapping_sha256' IS DISTINCT FROM
                        authoritative_feed.mapping_sha256
                    OR p_session_clock->>'feed_status' IS DISTINCT FROM
                        authoritative_feed.status
                    OR p_session_clock->'reason_codes' IS DISTINCT FROM
                        authoritative_feed.reason_codes
                    OR NULLIF(p_session_clock->>'heartbeat_at', '') IS NULL
                    OR (p_session_clock->>'heartbeat_at')::timestamptz IS DISTINCT FROM
                        authoritative_feed.heartbeat_at
                    OR NULLIF(p_session_clock->>'heartbeat_ttl_seconds', '') IS NULL
                    OR (p_session_clock->>'heartbeat_ttl_seconds')::integer IS DISTINCT FROM
                        authoritative_feed.heartbeat_ttl_seconds
                    OR NULLIF(p_session_clock->>'last_verified_source_as_of', '') IS NULL
                    OR (p_session_clock->>'last_verified_source_as_of')::timestamptz
                        IS DISTINCT FROM authoritative_feed.last_verified_source_as_of
                    OR p_session_clock->>'verification_payload_sha256' IS DISTINCT FROM
                        authoritative_feed.verification_payload_sha256
                    OR p_session_clock->'verification_snapshot' IS DISTINCT FROM
                        authoritative_feed.verification_snapshot
                ) THEN
                    RAISE EXCEPTION 'session-clock payload does not match authoritative feed state';
                END IF;

                IF feed_status = 'READY' THEN
                    authoritative_preflight := preflight_entry_signal_activation(
                        lifecycle_row.entry_signal_decision_id,
                        lifecycle_row.session_feed_state_id,
                        p_observed_at
                    );
                    observed_count :=
                        (authoritative_preflight->>'observed_sessions')::smallint;
                    observed_last_date := NULLIF(
                        authoritative_preflight->>'last_observed_market_date',
                        ''
                    )::date;
                    observed_last_as_of := NULLIF(
                        authoritative_preflight->>'last_observed_as_of',
                        ''
                    )::timestamptz;
                    observed_evidence := authoritative_preflight->'sessions';
                    observed_evidence_sha256 :=
                        authoritative_preflight->>'sessions_sha256';

                    IF COALESCE(
                        (authoritative_preflight->>'stale')::boolean,
                        FALSE
                    ) THEN
                        horizon_reached_at := observed_last_as_of;
                        horizon_market_date := observed_last_date;
                    END IF;

                    IF observed_count < lifecycle_row.observed_sessions
                       OR (
                           lifecycle_row.observed_sessions > 0
                           AND lifecycle_row.session_evidence <>
                               (
                                   SELECT COALESCE(
                                       jsonb_agg(value ORDER BY ordinality),
                                       '[]'::jsonb
                                   )
                                   FROM jsonb_array_elements(observed_evidence)
                                        WITH ORDINALITY AS prior(value, ordinality)
                                   WHERE ordinality <= lifecycle_row.observed_sessions
                               )
                       )
                    THEN
                        RAISE EXCEPTION 'session-clock evidence is not append-only';
                    END IF;
                END IF;

                IF p_exit IS NOT NULL
                   AND lifecycle_row.configured_exit_policy_name IS NOT NULL
                   AND NULLIF(p_exit->>'as_of', '') IS NOT NULL
                   AND p_exit->>'resolver_version' = 'exit-observation-resolver-v1'
                THEN
                    candidate_exit_as_of := (p_exit->>'as_of')::timestamptz;
                    exit_is_valid := COALESCE((
                        COALESCE((p_exit->>'signal')::boolean, FALSE)
                        AND (
                            (
                                p_exit->>'observation_role' = 'current_recommendation'
                                AND COALESCE((p_exit->>'state_only')::boolean, TRUE) = FALSE
                            )
                            OR (
                                p_exit->>'observation_role' IN (
                                    'state_seed', 'historical_backfill'
                                )
                                AND COALESCE((p_exit->>'state_only')::boolean, FALSE) = TRUE
                            )
                        )
                        AND (p_exit->>'valid_until')::timestamptz > candidate_exit_as_of
                        AND NULLIF(p_exit->>'source_event_key', '') IS NOT NULL
                        AND NULLIF(p_exit->>'source_system', '') IS NOT NULL
                        AND NULLIF(p_exit->>'payload_sha256', '') IS NOT NULL
                        AND NULLIF(p_exit->>'policy_name', '') IS NOT NULL
                        AND NULLIF(p_exit->>'policy_version', '') IS NOT NULL
                        AND (
                            (
                                p_exit->>'source_system' = 'serving'
                                AND (p_exit->>'instrument_id')::bigint =
                                    decision_source_instrument_id
                            )
                            OR (
                                p_exit->>'source_system' <> 'serving'
                                AND (p_exit->>'instrument_id')::bigint =
                                    lifecycle_row.instrument_id
                            )
                        )
                        AND p_exit->>'policy_name' = lifecycle_row.configured_exit_policy_name
                        AND p_exit->>'policy_version' = lifecycle_row.configured_exit_policy_version
                        AND (
                            lifecycle_row.configured_exit_profile_id IS NULL
                            OR (
                                (p_exit->>'profile_id')::bigint =
                                    lifecycle_row.configured_exit_profile_id
                                AND p_exit->>'profile_signature' =
                                    lifecycle_row.configured_exit_profile_signature
                            )
                        )
                        AND candidate_exit_as_of > lifecycle_row.activated_at
                        AND candidate_exit_as_of <= p_observed_at
                        AND (
                            horizon_reached_at IS NULL
                            OR candidate_exit_as_of <= horizon_reached_at
                        )
                    ), FALSE);
                END IF;

                IF p_exit IS NOT NULL AND NOT exit_is_valid THEN
                    RAISE EXCEPTION 'invalid or non-earliest exit observation';
                END IF;

                -- Chronologically earliest event wins; exact tie belongs to exit.
                IF exit_is_valid THEN
                    UPDATE entry_signal_lifecycles
                    SET status = 'CLOSED_EXIT',
                        observed_sessions = observed_count,
                        last_observed_market_date = observed_last_date,
                        last_observed_as_of = observed_last_as_of,
                        session_evidence_sha256 = observed_evidence_sha256,
                        session_evidence = observed_evidence,
                        session_clock_updated_at = clock_verified_at,
                        session_feed_status = feed_status,
                        session_feed_reason_codes = feed_reasons,
                        closed_at = clock_timestamp(),
                        closed_effective_as_of = candidate_exit_as_of,
                        close_reason = 'STOCK_SPECIFIC_EXIT',
                        exit_source_system = p_exit->>'source_system',
                        exit_source_event_key = p_exit->>'source_event_key',
                        exit_observation_id = NULLIF(p_exit->>'observation_id', '')::bigint,
                        exit_as_of = candidate_exit_as_of,
                        exit_policy_name = p_exit->>'policy_name',
                        exit_policy_version = p_exit->>'policy_version',
                        exit_payload_sha256 = p_exit->>'payload_sha256',
                        exit_snapshot = p_exit,
                        updated_at = clock_timestamp()
                    WHERE id = lifecycle_row.id;

                    RETURN lifecycle_row.id;
                END IF;

                IF horizon_reached_at IS NOT NULL THEN
                    UPDATE entry_signal_lifecycles
                    SET status = 'EXPIRED',
                        observed_sessions = observed_count,
                        last_observed_market_date = observed_last_date,
                        last_observed_as_of = observed_last_as_of,
                        session_evidence_sha256 = observed_evidence_sha256,
                        session_evidence = observed_evidence,
                        session_clock_updated_at = clock_verified_at,
                        session_feed_status = feed_status,
                        session_feed_reason_codes = feed_reasons,
                        expiry_market_date = horizon_market_date,
                        expires_at = horizon_reached_at,
                        closed_at = clock_timestamp(),
                        closed_effective_as_of = horizon_reached_at,
                        close_reason = 'HORIZON_EXPIRED',
                        updated_at = clock_timestamp()
                    WHERE id = lifecycle_row.id;

                    RETURN lifecycle_row.id;
                END IF;

                UPDATE entry_signal_lifecycles
                SET observed_sessions = observed_count,
                    last_observed_market_date = observed_last_date,
                    last_observed_as_of = observed_last_as_of,
                    session_evidence_sha256 = observed_evidence_sha256,
                    session_evidence = observed_evidence,
                    session_clock_updated_at = clock_verified_at,
                    session_feed_status = feed_status,
                    session_feed_reason_codes = feed_reasons,
                    updated_at = clock_timestamp()
                WHERE id = lifecycle_row.id;

                RETURN NULL;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW current_final_entry_signals AS
            SELECT
                decision.id AS decision_id,
                decision.user_id,
                decision.context_type,
                decision.context_key,
                decision.saved_prediction_filter_id,
                decision.saved_prediction_filter_id_snapshot,
                decision.instrument_id,
                decision.source_system,
                decision.source_prediction_id,
                decision.source_event_key,
                decision.source_payload_sha256,
                decision.source_batch_id,
                decision.source_release_id,
                decision.source_instrument_id,
                decision.source_as_of,
                decision.source_batch_completed_at,
                decision.source_market_date,
                decision.forecast_horizon_sessions,
                decision.source_variant,
                decision.raw_signal,
                decision.decision_signal AS final_signal,
                decision.reason_codes,
                decision.source_snapshot,
                decision.filter_snapshot,
                decision.filter_sha256,
                decision.evaluator_version,
                decision.cutover_at,
                lifecycle.id AS lifecycle_id,
                lifecycle.status AS lifecycle_status,
                lifecycle.entry_consumed_at,
                lifecycle.expires_at,
                lifecycle.expiry_market_date,
                lifecycle.session_feed_state_id,
                lifecycle.session_feed_provider,
                lifecycle.session_feed_resolver_version,
                lifecycle.session_mapping_sha256,
                lifecycle.session_timezone,
                lifecycle.observed_sessions,
                lifecycle.last_observed_market_date,
                lifecycle.last_observed_as_of,
                lifecycle.session_evidence_sha256,
                lifecycle.session_evidence,
                lifecycle.session_clock_updated_at,
                lifecycle.session_feed_status,
                lifecycle.session_feed_reason_codes,
                feed_state.status AS provider_feed_status,
                feed_state.reason_codes AS provider_feed_reason_codes,
                feed_state.heartbeat_at AS provider_heartbeat_at,
                feed_state.heartbeat_ttl_seconds AS provider_heartbeat_ttl_seconds,
                lifecycle.configured_exit_profile_id,
                lifecycle.configured_exit_profile_signature,
                lifecycle.configured_exit_policy_name,
                lifecycle.configured_exit_policy_version,
                lifecycle.configured_exit_snapshot
            FROM entry_signal_decisions AS decision
            JOIN entry_signal_lifecycles AS lifecycle
              ON lifecycle.entry_signal_decision_id = decision.id
            JOIN entry_signal_session_feed_states AS feed_state
              ON feed_state.id = lifecycle.session_feed_state_id
             AND feed_state.instrument_id = lifecycle.instrument_id
             AND feed_state.provider_name = lifecycle.session_feed_provider
             AND feed_state.resolver_version = lifecycle.session_feed_resolver_version
             AND feed_state.mapping_sha256 = lifecycle.session_mapping_sha256
             AND (
                 (
                     feed_state.provider_name = 'serving_prediction'
                     AND feed_state.source_instrument_id = decision.source_instrument_id
                 )
                 OR (
                     feed_state.provider_name = 'main_price_bar'
                     AND feed_state.source_instrument_id = decision.instrument_id
                 )
             )
             AND feed_state.verification_payload_sha256 = encode(
                 sha256(convert_to(feed_state.verification_snapshot::text, 'UTF8')),
                 'hex'
             )
            WHERE decision.decision_status = 'ACCEPTED'
              AND decision.raw_signal = 'BUY'
              AND decision.filters_passed = TRUE
              AND decision.decision_signal = 'BUY'
              AND lifecycle.status = 'ACTIVE'
              AND lifecycle.observed_sessions < decision.forecast_horizon_sessions
              AND lifecycle.session_feed_status = 'READY'
              AND feed_state.status = 'READY'
              AND feed_state.heartbeat_at +
                    make_interval(secs => feed_state.heartbeat_ttl_seconds) > CURRENT_TIMESTAMP
              AND feed_state.last_verified_source_as_of >= decision.source_as_of
              AND (
                  lifecycle.last_observed_as_of IS NULL
                  OR feed_state.last_verified_source_as_of >= lifecycle.last_observed_as_of
              )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS current_final_entry_signals');
        DB::statement(
            'DROP FUNCTION IF EXISTS advance_entry_signal_lifecycle(bigint, varchar, bigint, timestamptz, jsonb, jsonb)',
        );
        Schema::dropIfExists('entry_signal_lifecycles');
        DB::statement('DROP FUNCTION IF EXISTS guard_entry_signal_lifecycle()');
        DB::statement('DROP FUNCTION IF EXISTS preflight_entry_signal_activation(bigint, bigint, timestamptz)');
        Schema::dropIfExists('entry_signal_decisions');
        DB::statement('DROP FUNCTION IF EXISTS require_accepted_decision_lifecycle()');
        DB::statement('DROP FUNCTION IF EXISTS reject_entry_signal_decision_update()');
        DB::statement('DROP FUNCTION IF EXISTS record_entry_signal_session_feed_state(bigint, varchar, varchar, bigint, char, varchar, jsonb, timestamptz, integer, timestamptz, char, jsonb)');
        Schema::dropIfExists('entry_signal_session_feed_states');
        DB::statement('DROP FUNCTION IF EXISTS guard_entry_signal_session_feed_state()');
        Schema::dropIfExists('entry_signal_runtime_config');
        DB::statement('DROP FUNCTION IF EXISTS reject_entry_signal_runtime_config_mutation()');
    }
};
