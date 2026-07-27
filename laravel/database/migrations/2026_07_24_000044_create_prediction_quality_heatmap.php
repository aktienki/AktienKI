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
            $table->string('quality_score_band', 30)->nullable();
            $table->string('quality_return_band', 30)->nullable();
            $table->string('quality_cell_key', 70)->nullable();
            $table->index(
                [
                    'quality_grid_version',
                    'quality_score_band',
                    'quality_return_band',
                ],
                'predictions_quality_heatmap_cell_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE predictions
            SET quality_score_band =
                    metadata->'quality_grid'->'score_band'->>'code',
                quality_return_band =
                    metadata->'quality_grid'->'return_band'->>'code',
                quality_cell_key = concat_ws(
                    '__',
                    metadata->'quality_grid'->'score_band'->>'code',
                    metadata->'quality_grid'->'return_band'->>'code'
                ),
                quality_score_lower = NULLIF(
                    metadata->'quality_grid'->'score_band'->>'score_lower', ''
                )::numeric,
                quality_score_upper = NULLIF(
                    metadata->'quality_grid'->'score_band'->>'score_upper', ''
                )::numeric,
                quality_return_lower = NULLIF(
                    metadata->'quality_grid'->'return_band'->>'return_lower', ''
                )::numeric,
                quality_return_upper = NULLIF(
                    metadata->'quality_grid'->'return_band'->>'return_upper', ''
                )::numeric
            WHERE jsonb_exists(metadata, 'quality_grid')
            SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW prediction_quality_heatmap AS
            SELECT quality_grid_version,
                   quality_score_band,
                   quality_return_band,
                   quality_cell_key,
                   quality_score_lower,
                   quality_score_upper,
                   quality_return_lower,
                   quality_return_upper,
                   ai_type,
                   timeframe,
                   COUNT(*) AS prediction_count,
                   COUNT(actual_return) AS validated_count,
                   AVG(actual_return) AS average_actual_return,
                   PERCENTILE_CONT(0.5) WITHIN GROUP (
                       ORDER BY actual_return
                   ) AS median_actual_return,
                   AVG(realized_strategy_return) AS average_strategy_return,
                   AVG(direction_correct::int) AS direction_accuracy,
                   SUM(CASE WHEN actual_return > 0 THEN 1 ELSE 0 END)
                       AS positive_return_count,
                   MIN(actual_return) AS minimum_actual_return,
                   MAX(actual_return) AS maximum_actual_return,
                   MAX(validated_at) AS last_validated_at
            FROM predictions
            WHERE quality_cell_key IS NOT NULL
            GROUP BY quality_grid_version,
                     quality_score_band,
                     quality_return_band,
                     quality_cell_key,
                     quality_score_lower,
                     quality_score_upper,
                     quality_return_lower,
                     quality_return_upper,
                     ai_type,
                     timeframe
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS prediction_quality_heatmap');
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_quality_heatmap_cell_idx');
            $table->dropColumn([
                'quality_score_band',
                'quality_return_band',
                'quality_cell_key',
            ]);
        });
    }
};
