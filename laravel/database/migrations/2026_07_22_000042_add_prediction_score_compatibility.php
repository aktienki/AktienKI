<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE predictions
            ADD COLUMN IF NOT EXISTS prediction_score numeric(8, 4)
            GENERATED ALWAYS AS (ai_score) STORED
            SQL);

        DB::statement(
            'CREATE INDEX IF NOT EXISTS predictions_prediction_score_idx ON predictions (prediction_score DESC)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS predictions_prediction_score_idx');
        DB::statement('ALTER TABLE predictions DROP COLUMN IF EXISTS prediction_score');
    }
};
