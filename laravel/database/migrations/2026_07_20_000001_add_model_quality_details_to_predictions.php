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
            $table->jsonb('factor_ratings')->nullable();
            $table->string('factor_ratings_version', 20)->nullable();
            $table->decimal('signal_quality_score', 8, 6)->nullable();
            $table->decimal('drawdown_risk_factor', 8, 6)->nullable();
            $table->string('drawdown_risk_level', 24)->nullable();
            $table->string('backtest_version', 40)->nullable();
            $table->string('quality_score_version', 40)->nullable();
            $table->jsonb('ensemble_quality')->nullable();
            $table->string('general_score_source', 24)->nullable();

            $table->index(
                ['factor_ratings_version'],
                'predictions_factor_ratings_version_idx'
            );
            $table->index(
                ['signal_quality_score'],
                'predictions_signal_quality_score_idx'
            );
            $table->index(
                ['drawdown_risk_level', 'drawdown_risk_factor'],
                'predictions_drawdown_risk_idx'
            );
        });

        // Existing Python predictions already contain these values in JSON.
        // Backfill makes them directly queryable without changing history.
        DB::statement(<<<'SQL'
            UPDATE predictions
            SET factor_ratings = COALESCE(
                    factor_ratings,
                    metadata->'factor_ratings',
                    explanation->'factor_ratings'
                ),
                factor_ratings_version = COALESCE(
                    factor_ratings_version,
                    metadata->>'factor_ratings_version'
                ),
                signal_quality_score = COALESCE(
                    signal_quality_score,
                    NULLIF(metadata->>'model_selection_score', '')::numeric
                ),
                drawdown_risk_factor = COALESCE(
                    drawdown_risk_factor,
                    NULLIF(
                        metadata->'factor_ratings'->'drawdown_risk'->>'value',
                        ''
                    )::numeric
                ),
                backtest_version = COALESCE(
                    backtest_version,
                    metadata->>'backtest_version'
                ),
                quality_score_version = COALESCE(
                    quality_score_version,
                    metadata->>'quality_score_version'
                ),
                ensemble_quality = COALESCE(
                    ensemble_quality,
                    metadata->'ensemble_quality'
                ),
                general_score_source = COALESCE(
                    general_score_source,
                    metadata->>'general_score_source'
                )
            WHERE factor_ratings IS NULL
               OR factor_ratings_version IS NULL
               OR signal_quality_score IS NULL
               OR drawdown_risk_factor IS NULL
               OR backtest_version IS NULL
               OR quality_score_version IS NULL
               OR ensemble_quality IS NULL
               OR general_score_source IS NULL
            SQL);

        DB::statement(<<<'SQL'
            UPDATE predictions
            SET drawdown_risk_level = CASE
                WHEN drawdown_risk_factor IS NULL THEN drawdown_risk_level
                WHEN drawdown_risk_factor <= 0.10 THEN 'low'
                WHEN drawdown_risk_factor <= 0.20 THEN 'moderate'
                WHEN drawdown_risk_factor <= 0.30 THEN 'elevated'
                WHEN drawdown_risk_factor <= 0.40 THEN 'high'
                ELSE 'very_high'
            END
            WHERE drawdown_risk_level IS NULL
            SQL);

        DB::statement(
            'CREATE INDEX predictions_factor_ratings_gin_idx '
            .'ON predictions USING GIN (factor_ratings)'
        );

        // Keep materialized columns synchronized for predictions written by
        // Python or Laravel while JSON remains the transport contract.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_prediction_quality_details()
            RETURNS trigger AS $$
            BEGIN
                NEW.factor_ratings := COALESCE(
                    NEW.factor_ratings,
                    NEW.metadata->'factor_ratings',
                    NEW.explanation->'factor_ratings'
                );
                NEW.factor_ratings_version := COALESCE(
                    NEW.factor_ratings_version,
                    NEW.metadata->>'factor_ratings_version'
                );
                NEW.signal_quality_score := COALESCE(
                    NEW.signal_quality_score,
                    NULLIF(NEW.metadata->>'model_selection_score', '')::numeric
                );
                NEW.drawdown_risk_factor := COALESCE(
                    NEW.drawdown_risk_factor,
                    NULLIF(
                        NEW.metadata->'factor_ratings'->'drawdown_risk'->>'value',
                        ''
                    )::numeric
                );
                NEW.backtest_version := COALESCE(
                    NEW.backtest_version,
                    NEW.metadata->>'backtest_version'
                );
                NEW.quality_score_version := COALESCE(
                    NEW.quality_score_version,
                    NEW.metadata->>'quality_score_version'
                );
                NEW.ensemble_quality := COALESCE(
                    NEW.ensemble_quality,
                    NEW.metadata->'ensemble_quality'
                );
                NEW.general_score_source := COALESCE(
                    NEW.general_score_source,
                    NEW.metadata->>'general_score_source'
                );
                IF NEW.drawdown_risk_factor IS NOT NULL THEN
                    NEW.drawdown_risk_level := CASE
                        WHEN NEW.drawdown_risk_factor <= 0.10 THEN 'low'
                        WHEN NEW.drawdown_risk_factor <= 0.20 THEN 'moderate'
                        WHEN NEW.drawdown_risk_factor <= 0.30 THEN 'elevated'
                        WHEN NEW.drawdown_risk_factor <= 0.40 THEN 'high'
                        ELSE 'very_high'
                    END;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER predictions_sync_quality_details
            BEFORE INSERT OR UPDATE OF metadata, explanation, factor_ratings
            ON predictions
            FOR EACH ROW
            EXECUTE FUNCTION sync_prediction_quality_details()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS predictions_sync_quality_details ON predictions'
        );
        DB::statement('DROP FUNCTION IF EXISTS sync_prediction_quality_details()');
        DB::statement('DROP INDEX IF EXISTS predictions_factor_ratings_gin_idx');

        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_factor_ratings_version_idx');
            $table->dropIndex('predictions_signal_quality_score_idx');
            $table->dropIndex('predictions_drawdown_risk_idx');
            $table->dropColumn([
                'factor_ratings',
                'factor_ratings_version',
                'signal_quality_score',
                'drawdown_risk_factor',
                'drawdown_risk_level',
                'backtest_version',
                'quality_score_version',
                'ensemble_quality',
                'general_score_source',
            ]);
        });
    }
};
