<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX trained_models_single_active_scope_idx
            ON trained_models (
                instrument_id, ai_type, timeframe,
                prediction_horizon_minutes, feature_set_version
            )
            WHERE status = 'active'
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS trained_models_single_active_scope_idx'
        );
    }
};
