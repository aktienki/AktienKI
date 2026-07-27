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
            $table->timestampTz('source_bar_time')->nullable();
        });
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX predictions_source_bar_idempotency_idx
            ON predictions (
                instrument_id, ai_type, timeframe,
                prediction_horizon_minutes, source_bar_time
            )
            WHERE source_bar_time IS NOT NULL
            SQL);
        $this->rewriteView(true);
    }

    public function down(): void
    {
        $this->rewriteView(false);
        DB::statement(
            'DROP INDEX IF EXISTS predictions_source_bar_idempotency_idx'
        );
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropColumn('source_bar_time');
        });
    }

    private function rewriteView(bool $append): void
    {
        $row = DB::selectOne(
            "SELECT pg_get_viewdef('public_prediction_models'::regclass, true) AS definition"
        );
        $marker = "\n   FROM predictions p";
        $extra = ",\n    p.source_bar_time";
        if ($append) {
            if (!str_contains($row->definition, $marker)) {
                throw new RuntimeException('Unexpected public prediction view');
            }
            $view = str_replace($marker, $extra . $marker, $row->definition);
        } else {
            $view = str_replace($extra, '', $row->definition, $count);
            if ($count !== 1) {
                throw new RuntimeException('Source bar column not found');
            }
        }
        DB::statement('DROP VIEW public_prediction_models');
        DB::statement('CREATE VIEW public_prediction_models AS ' . $view);
    }
};
