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
            $table->string('public_alias', 100)->nullable();
            $table->string('public_code', 20)->nullable();
            $table->string('public_description', 255)->nullable();
            $table->boolean('is_public')->default(true);
            $table->index(['public_code', 'interval'], 'model_definitions_public_code_idx');
        });

        Schema::table('trained_models', function (Blueprint $table): void {
            $table->jsonb('directional_eligibility')->nullable();
            $table->index('directional_eligibility', 'trained_models_directional_eligibility_idx', 'gin');
        });

        Schema::table('predictions', function (Blueprint $table): void {
            $table->string('signal_source', 40)->default('standard_ensemble');
            $table->boolean('fallback_used')->default(false);
            $table->string('hurdle_long_status', 20)->nullable();
            $table->string('hurdle_short_status', 20)->nullable();
            $table->index(
                ['signal_source', 'fallback_used'],
                'predictions_signal_source_idx'
            );
            $table->index(
                ['hurdle_long_status', 'hurdle_short_status'],
                'predictions_hurdle_status_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE model_definitions
            SET public_alias = (CASE WHEN interval = '1h' THEN 'Pulse ' ELSE 'Horizon ' END) ||
                CASE algorithm
                    WHEN 'hist_gradient_boosting_regressor' THEN 'Nova'
                    WHEN 'extra_trees_regressor' THEN 'Atlas'
                    WHEN 'random_forest_regressor' THEN 'Orion'
                    WHEN 'gradient_boosting_regressor' THEN 'Vega'
                    WHEN 'catboost_regressor' THEN 'Helios'
                    WHEN 'lightgbm_regressor' THEN 'Lumina'
                    WHEN 'directional_hurdle_random_forest' THEN 'Aegis'
                    ELSE 'Core'
                END,
                public_code = (CASE WHEN interval = '1h' THEN 'PN-' ELSE 'HH-' END) ||
                CASE algorithm
                    WHEN 'hist_gradient_boosting_regressor' THEN '01'
                    WHEN 'extra_trees_regressor' THEN '02'
                    WHEN 'random_forest_regressor' THEN '03'
                    WHEN 'gradient_boosting_regressor' THEN '04'
                    WHEN 'catboost_regressor' THEN '05'
                    WHEN 'lightgbm_regressor' THEN '06'
                    WHEN 'directional_hurdle_random_forest' THEN '07'
                    ELSE '99'
                END,
                public_description = CASE algorithm
                    WHEN 'directional_hurdle_random_forest' THEN 'Directional specialist'
                    ELSE 'Market model'
                END
            WHERE public_alias IS NULL OR public_code IS NULL
            SQL);

        DB::statement(<<<'SQL'
            UPDATE trained_models
            SET directional_eligibility = COALESCE(
                directional_eligibility,
                metrics->'directional_eligibility',
                metadata->'directional_eligibility'
            )
            WHERE directional_eligibility IS NULL
            SQL);

        DB::statement(<<<'SQL'
            UPDATE predictions
            SET signal_source = COALESCE(metadata->>'signal_source', signal_source),
                fallback_used = COALESCE(
                    (metadata->>'fallback_used')::boolean, fallback_used
                ),
                hurdle_long_status = COALESCE(
                    metadata->'directional_routing'->'eligibility'->'long'->>'status',
                    hurdle_long_status
                ),
                hurdle_short_status = COALESCE(
                    metadata->'directional_routing'->'eligibility'->'short'->>'status',
                    hurdle_short_status
                )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_directional_model_details()
            RETURNS trigger AS $$
            BEGIN
                NEW.directional_eligibility := COALESCE(
                    NEW.directional_eligibility,
                    NEW.metrics->'directional_eligibility',
                    NEW.metadata->'directional_eligibility'
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trained_models_sync_directional_details
            BEFORE INSERT OR UPDATE OF metrics, metadata, directional_eligibility
            ON trained_models FOR EACH ROW
            EXECUTE FUNCTION sync_directional_model_details();

            CREATE OR REPLACE FUNCTION sync_prediction_routing_details()
            RETURNS trigger AS $$
            BEGIN
                NEW.signal_source := COALESCE(
                    NEW.metadata->>'signal_source', NEW.signal_source,
                    'standard_ensemble'
                );
                NEW.fallback_used := COALESCE(
                    (NEW.metadata->>'fallback_used')::boolean,
                    NEW.fallback_used, FALSE
                );
                NEW.hurdle_long_status := COALESCE(
                    NEW.metadata->'directional_routing'->'eligibility'->'long'->>'status',
                    NEW.hurdle_long_status
                );
                NEW.hurdle_short_status := COALESCE(
                    NEW.metadata->'directional_routing'->'eligibility'->'short'->>'status',
                    NEW.hurdle_short_status
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER predictions_sync_routing_details
            BEFORE INSERT OR UPDATE OF metadata, signal_source, fallback_used
            ON predictions FOR EACH ROW
            EXECUTE FUNCTION sync_prediction_routing_details();
            SQL);

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

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS public_prediction_models');
        DB::statement('DROP VIEW IF EXISTS public_model_catalog');
        DB::statement('DROP TRIGGER IF EXISTS predictions_sync_routing_details ON predictions');
        DB::statement('DROP FUNCTION IF EXISTS sync_prediction_routing_details()');
        DB::statement('DROP TRIGGER IF EXISTS trained_models_sync_directional_details ON trained_models');
        DB::statement('DROP FUNCTION IF EXISTS sync_directional_model_details()');

        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_signal_source_idx');
            $table->dropIndex('predictions_hurdle_status_idx');
            $table->dropColumn([
                'signal_source', 'fallback_used',
                'hurdle_long_status', 'hurdle_short_status',
            ]);
        });
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->dropIndex('trained_models_directional_eligibility_idx');
            $table->dropColumn('directional_eligibility');
        });
        Schema::table('model_definitions', function (Blueprint $table): void {
            $table->dropIndex('model_definitions_public_code_idx');
            $table->dropColumn([
                'public_alias', 'public_code', 'public_description', 'is_public',
            ]);
        });
    }
};
