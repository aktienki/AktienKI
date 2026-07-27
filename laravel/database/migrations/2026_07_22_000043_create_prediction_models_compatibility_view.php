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

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW prediction_models AS
            SELECT
                p.id,
                p.id AS prediction_id,
                COALESCE(md.public_alias, md.name, md.code, 'AI model') AS model,
                COALESCE(p.ai_score, 0)::numeric AS score,
                COALESCE(
                    NULLIF(tm.metrics->>'accuracy', '')::numeric,
                    NULLIF(tm.metrics->>'direction_accuracy', '')::numeric,
                    p.live_direction_accuracy,
                    p.confidence,
                    0
                )::numeric AS accuracy,
                COALESCE(p.confidence, 0)::numeric AS confidence,
                COALESCE(
                    NULLIF(tm.metrics->>'hitrate', '')::numeric,
                    NULLIF(tm.metrics->>'hit_rate', '')::numeric,
                    p.live_direction_accuracy,
                    0
                )::numeric AS hitrate,
                tm.metrics,
                p.created_at,
                p.updated_at
            FROM predictions p
            LEFT JOIN trained_models tm ON tm.id = p.trained_model_id
            LEFT JOIN model_definitions md ON md.id = tm.model_definition_id
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS prediction_models');
        }
    }
};
