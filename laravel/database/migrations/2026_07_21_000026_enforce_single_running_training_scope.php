<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX training_runs_single_running_scope_idx
            ON training_runs (instrument_id, ai_type, timeframe)
            WHERE status = 'running'
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS training_runs_single_running_scope_idx'
        );
    }
};
