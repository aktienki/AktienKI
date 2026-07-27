<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->decimal('trigger_confirmation_score', 8, 6)->nullable();
            $table->decimal('trigger_bonus', 8, 6)->nullable();
            $table->decimal('trigger_penalty', 8, 6)->nullable();
            $table->unsignedSmallInteger('trigger_confirmation_count')->default(0);
            $table->boolean('trigger_release_used')->default(false);
            $table->boolean('trigger_veto_used')->default(false);
            $table->jsonb('trigger_details')->default(DB::raw("'{}'::jsonb"));
            $table->index(
                ['trigger_release_used', 'trigger_veto_used'],
                'predictions_trigger_decision_idx'
            );
            $table->index(
                'trigger_confirmation_score',
                'predictions_trigger_score_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE predictions SET
                trigger_confirmation_score = NULLIF(
                    metadata->'recommendation_verification'->>'trigger_adjusted_score', ''
                )::numeric,
                trigger_bonus = NULLIF(
                    metadata->'trigger_confirmation'->>'bonus', ''
                )::numeric,
                trigger_penalty = NULLIF(
                    metadata->'trigger_confirmation'->>'penalty', ''
                )::numeric,
                trigger_confirmation_count = COALESCE((
                    metadata->'trigger_confirmation'->>'confirmation_count'
                )::smallint, 0),
                trigger_release_used = COALESCE((
                    metadata->'recommendation_verification'->>'trigger_release_used'
                )::boolean, false),
                trigger_veto_used = COALESCE((
                    metadata->'recommendation_verification'->>'trigger_veto_used'
                )::boolean, false),
                trigger_details = COALESCE(
                    metadata->'trigger_confirmation', '{}'::jsonb
                )
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_prediction_trigger_details()
            RETURNS trigger AS $$
            BEGIN
                NEW.trigger_confirmation_score := COALESCE(
                    NULLIF(NEW.metadata->'recommendation_verification'->>'trigger_adjusted_score', '')::numeric,
                    NEW.trigger_confirmation_score
                );
                NEW.trigger_bonus := COALESCE(
                    NULLIF(NEW.metadata->'trigger_confirmation'->>'bonus', '')::numeric,
                    NEW.trigger_bonus
                );
                NEW.trigger_penalty := COALESCE(
                    NULLIF(NEW.metadata->'trigger_confirmation'->>'penalty', '')::numeric,
                    NEW.trigger_penalty
                );
                NEW.trigger_confirmation_count := COALESCE((
                    NEW.metadata->'trigger_confirmation'->>'confirmation_count'
                )::smallint, NEW.trigger_confirmation_count, 0);
                NEW.trigger_release_used := COALESCE((
                    NEW.metadata->'recommendation_verification'->>'trigger_release_used'
                )::boolean, NEW.trigger_release_used, false);
                NEW.trigger_veto_used := COALESCE((
                    NEW.metadata->'recommendation_verification'->>'trigger_veto_used'
                )::boolean, NEW.trigger_veto_used, false);
                NEW.trigger_details := COALESCE(
                    NEW.metadata->'trigger_confirmation', NEW.trigger_details, '{}'::jsonb
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER predictions_sync_trigger_details
            BEFORE INSERT OR UPDATE OF metadata, trigger_confirmation_score,
                trigger_bonus, trigger_penalty, trigger_confirmation_count,
                trigger_release_used, trigger_veto_used, trigger_details
            ON predictions FOR EACH ROW
            EXECUTE FUNCTION sync_prediction_trigger_details()
            SQL);

        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        DB::statement(<<<'SQL'
            CREATE VIEW public_prediction_models AS
            SELECT p.id, p.instrument_id, p.ai_type, p.prediction_time, p.timeframe,
                   p.prediction_horizon_minutes, p.signal, p.ai_score,
                   p.signal_source, p.fallback_used,
                   p.hurdle_long_status, p.hurdle_short_status,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.ai_type = 'pulse' THEN 'PN-07' ELSE 'HH-07' END
                        ELSE md.public_code END::varchar(20) AS public_code,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.ai_type = 'pulse' THEN 'Pulse Aegis' ELSE 'Horizon Aegis' END
                        ELSE md.public_alias END::varchar(100) AS public_alias,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN 'Directional specialist'
                        ELSE md.public_description END::varchar(255) AS public_description,
                   p.trigger_confirmation_score, p.trigger_bonus, p.trigger_penalty,
                   p.trigger_confirmation_count, p.trigger_release_used,
                   p.trigger_veto_used, p.trigger_details
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        DB::statement('DROP TRIGGER IF EXISTS predictions_sync_trigger_details ON predictions');
        DB::statement('DROP FUNCTION IF EXISTS sync_prediction_trigger_details()');
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_trigger_decision_idx');
            $table->dropIndex('predictions_trigger_score_idx');
            $table->dropColumn([
                'trigger_confirmation_score', 'trigger_bonus', 'trigger_penalty',
                'trigger_confirmation_count', 'trigger_release_used',
                'trigger_veto_used', 'trigger_details',
            ]);
        });
        DB::statement(<<<'SQL'
            CREATE VIEW public_prediction_models AS
            SELECT p.id, p.instrument_id, p.ai_type, p.prediction_time, p.timeframe,
                   p.prediction_horizon_minutes, p.signal, p.ai_score,
                   p.signal_source, p.fallback_used,
                   p.hurdle_long_status, p.hurdle_short_status,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.ai_type = 'pulse' THEN 'PN-07' ELSE 'HH-07' END
                        ELSE md.public_code END::varchar(20) AS public_code,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.ai_type = 'pulse' THEN 'Pulse Aegis' ELSE 'Horizon Aegis' END
                        ELSE md.public_alias END::varchar(100) AS public_alias,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN 'Directional specialist'
                        ELSE md.public_description END::varchar(255) AS public_description
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }
};
