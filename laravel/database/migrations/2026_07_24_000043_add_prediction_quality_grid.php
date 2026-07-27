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
            $table->string('quality_grid_version', 30)->nullable();
            $table->string('quality_band', 30)->nullable();
            $table->decimal('quality_score_lower', 8, 6)->nullable();
            $table->decimal('quality_score_upper', 8, 6)->nullable();
            $table->decimal('quality_return_lower', 10, 6)->nullable();
            $table->decimal('quality_return_upper', 10, 6)->nullable();
            $table->index(
                ['quality_grid_version', 'quality_band'],
                'predictions_quality_grid_band_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE predictions
            SET quality_grid_version = metadata->'quality_grid'->>'version',
                quality_band = metadata->'quality_grid'->'combined_band'->>'code',
                quality_score_lower = NULLIF(
                    metadata->'quality_grid'->'combined_band'->>'score_lower', ''
                )::numeric,
                quality_score_upper = NULLIF(
                    metadata->'quality_grid'->'combined_band'->>'score_upper', ''
                )::numeric,
                quality_return_lower = NULLIF(
                    metadata->'quality_grid'->'combined_band'->>'return_lower', ''
                )::numeric,
                quality_return_upper = NULLIF(
                    metadata->'quality_grid'->'combined_band'->>'return_upper', ''
                )::numeric
            WHERE jsonb_exists(metadata, 'quality_grid')
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE predictions
            ADD CONSTRAINT predictions_quality_grid_bounds_chk CHECK (
                (quality_score_lower IS NULL AND quality_score_upper IS NULL)
                OR (
                    quality_score_lower >= 0
                    AND quality_score_upper <= 1
                    AND quality_score_lower < quality_score_upper
                    AND quality_return_lower < quality_return_upper
                )
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE predictions '
            .'DROP CONSTRAINT IF EXISTS predictions_quality_grid_bounds_chk'
        );
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_quality_grid_band_idx');
            $table->dropColumn([
                'quality_grid_version',
                'quality_band',
                'quality_score_lower',
                'quality_score_upper',
                'quality_return_lower',
                'quality_return_upper',
            ]);
        });
    }
};
