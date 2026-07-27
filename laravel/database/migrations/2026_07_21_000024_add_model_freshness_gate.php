<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'model_freshness_status', 'model_age_days',
        'model_retrain_after_days', 'model_freshness_veto_used',
        'model_freshness_details',
    ];

    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->string('model_freshness_status', 24)->default('unknown');
            $table->decimal('model_age_days', 12, 6)->nullable();
            $table->unsignedSmallInteger('model_retrain_after_days')->nullable();
            $table->boolean('model_freshness_veto_used')->default(false);
            $table->jsonb('model_freshness_details')->default(DB::raw("'{}'::jsonb"));
            $table->index(
                ['model_freshness_status', 'model_freshness_veto_used'],
                'predictions_model_freshness_idx'
            );
        });
        $this->rewriteView(true);
    }

    public function down(): void
    {
        $this->rewriteView(false);
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_model_freshness_idx');
            $table->dropColumn(self::COLUMNS);
        });
    }

    private function rewriteView(bool $append): void
    {
        $row = DB::selectOne(
            "SELECT pg_get_viewdef('public_prediction_models'::regclass, true) AS definition"
        );
        $marker = "\n   FROM predictions p";
        $extra = ",\n    p." . implode(",\n    p.", self::COLUMNS);
        if ($append) {
            if (!str_contains($row->definition, $marker)) {
                throw new RuntimeException('Unexpected public prediction view');
            }
            $view = str_replace($marker, $extra . $marker, $row->definition);
        } else {
            $view = str_replace($extra, '', $row->definition, $count);
            if ($count !== 1) {
                throw new RuntimeException('Model freshness columns not found');
            }
        }
        DB::statement('DROP VIEW public_prediction_models');
        DB::statement('CREATE VIEW public_prediction_models AS ' . $view);
    }
};
