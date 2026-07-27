<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_definitions', function (Blueprint $table): void {
            $table->string('ai_type', 20)->default('horizon');
            $table->index(['ai_type', 'interval'], 'model_definitions_ai_type_idx');
        });
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->string('ai_type', 20)->default('horizon');
            $table->index(
                ['instrument_id', 'ai_type', 'timeframe', 'prediction_horizon_minutes'],
                'trained_models_ai_lookup_idx'
            );
        });
        Schema::table('training_runs', function (Blueprint $table): void {
            $table->string('ai_type', 20)->default('horizon');
            $table->index(
                ['instrument_id', 'ai_type', 'timeframe', 'status'],
                'training_runs_ai_lookup_idx'
            );
        });
        Schema::table('predictions', function (Blueprint $table): void {
            $table->string('ai_type', 20)->default('horizon');
            $table->index(
                ['instrument_id', 'ai_type', 'timeframe', 'prediction_horizon_minutes'],
                'predictions_ai_lookup_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE model_definitions
            SET ai_type = CASE
                WHEN public_code LIKE 'PN-%' OR interval = '1h' THEN 'pulse'
                WHEN interval = '15m' THEN 'forex_15m'
                ELSE 'horizon'
            END
            SQL);
        DB::statement(<<<'SQL'
            UPDATE trained_models
            SET ai_type = COALESCE(NULLIF(metadata->>'ai_type', ''),
                CASE WHEN timeframe = '1h' THEN 'pulse'
                     WHEN timeframe = '15m' THEN 'forex_15m'
                     ELSE 'horizon' END)
            SQL);
        DB::statement(<<<'SQL'
            UPDATE training_runs
            SET ai_type = COALESCE(NULLIF(resolved_configuration->>'ai_type', ''),
                CASE WHEN timeframe = '1h' THEN 'pulse'
                     WHEN timeframe = '15m' THEN 'forex_15m'
                     ELSE 'horizon' END)
            SQL);
        DB::statement(<<<'SQL'
            UPDATE predictions
            SET ai_type = COALESCE(NULLIF(metadata->>'ai_type', ''),
                CASE WHEN timeframe = '1h' THEN 'pulse'
                     WHEN timeframe = '15m' THEN 'forex_15m'
                     ELSE 'horizon' END)
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_ai_type_details()
            RETURNS trigger AS $$
            BEGIN
                NEW.ai_type := COALESCE(NULLIF(NEW.metadata->>'ai_type', ''),
                    NEW.ai_type, CASE WHEN NEW.timeframe = '1h' THEN 'pulse'
                    WHEN NEW.timeframe = '15m' THEN 'forex_15m' ELSE 'horizon' END);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER trained_models_sync_ai_type
            BEFORE INSERT OR UPDATE OF metadata, ai_type ON trained_models
            FOR EACH ROW EXECUTE FUNCTION sync_ai_type_details()
            SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER predictions_sync_ai_type
            BEFORE INSERT OR UPDATE OF metadata, ai_type ON predictions
            FOR EACH ROW EXECUTE FUNCTION sync_ai_type_details()
            SQL);

        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        DB::statement('DROP VIEW IF EXISTS public_model_catalog');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW public_model_catalog AS
            SELECT tm.id, tm.instrument_id, tm.ai_type,
                   md.public_code, md.public_alias, md.public_description,
                   tm.timeframe, tm.prediction_horizon_minutes, tm.model_role,
                   tm.status, tm.trained_at,
                   tm.metrics->>'selection_score' AS quality_score,
                   tm.metrics->'directional_eligibility' AS directional_eligibility
            FROM trained_models tm
            JOIN model_definitions md ON md.id = tm.model_definition_id
            WHERE md.is_public = TRUE
            SQL);
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW public_prediction_models AS
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

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        DB::statement('DROP VIEW IF EXISTS public_model_catalog');
        DB::statement('DROP TRIGGER IF EXISTS predictions_sync_ai_type ON predictions');
        DB::statement('DROP TRIGGER IF EXISTS trained_models_sync_ai_type ON trained_models');
        DB::statement('DROP FUNCTION IF EXISTS sync_ai_type_details()');
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_ai_lookup_idx');
            $table->dropColumn('ai_type');
        });
        Schema::table('training_runs', function (Blueprint $table): void {
            $table->dropIndex('training_runs_ai_lookup_idx');
            $table->dropColumn('ai_type');
        });
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->dropIndex('trained_models_ai_lookup_idx');
            $table->dropColumn('ai_type');
        });
        Schema::table('model_definitions', function (Blueprint $table): void {
            $table->dropIndex('model_definitions_ai_type_idx');
            $table->dropColumn('ai_type');
        });
        DB::statement(<<<'SQL'
            CREATE VIEW public_model_catalog AS
            SELECT tm.id, tm.instrument_id, md.public_code, md.public_alias,
                   md.public_description, tm.timeframe,
                   tm.prediction_horizon_minutes, tm.model_role, tm.status,
                   tm.trained_at, tm.metrics->>'selection_score' AS quality_score,
                   tm.metrics->'directional_eligibility' AS directional_eligibility
            FROM trained_models tm
            JOIN model_definitions md ON md.id = tm.model_definition_id
            WHERE md.is_public = TRUE
            SQL);
        DB::statement(<<<'SQL'
            CREATE VIEW public_prediction_models AS
            SELECT p.id, p.instrument_id, p.prediction_time, p.timeframe,
                   p.prediction_horizon_minutes, p.signal, p.ai_score,
                   p.signal_source, p.fallback_used,
                   p.hurdle_long_status, p.hurdle_short_status,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.timeframe = '1h' THEN 'PN-07' ELSE 'HH-07' END
                        ELSE md.public_code END::varchar(20) AS public_code,
                   CASE WHEN p.signal_source = 'directional_hurdle'
                        THEN CASE WHEN p.timeframe = '1h' THEN 'Pulse Aegis' ELSE 'Horizon Aegis' END
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
