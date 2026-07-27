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
            $table->string('feature_drift_status', 24)->default('unknown');
            $table->decimal('feature_drift_max_zscore', 12, 6)->nullable();
            $table->decimal('feature_drift_ratio', 8, 6)->nullable();
            $table->boolean('feature_drift_veto_used')->default(false);
            $table->jsonb('feature_drift_details')->default(DB::raw("'{}'::jsonb"));
            $table->index(
                ['feature_drift_status', 'feature_drift_veto_used'],
                'predictions_feature_drift_idx'
            );
        });
        $this->appendViewColumns();
    }

    public function down(): void
    {
        $this->removeViewColumns();
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_feature_drift_idx');
            $table->dropColumn([
                'feature_drift_status', 'feature_drift_max_zscore',
                'feature_drift_ratio', 'feature_drift_veto_used',
                'feature_drift_details',
            ]);
        });
    }

    private function appendViewColumns(): void
    {
        $row = DB::selectOne(
            "SELECT pg_get_viewdef('public_prediction_models'::regclass, true) AS definition"
        );
        $marker = "\n   FROM predictions p";
        if (!str_contains($row->definition, $marker)) {
            throw new RuntimeException('Unexpected public_prediction_models definition');
        }
        $extra = ",\n    p.feature_drift_status,\n"
            . "    p.feature_drift_max_zscore,\n"
            . "    p.feature_drift_ratio,\n"
            . "    p.feature_drift_veto_used,\n"
            . "    p.feature_drift_details";
        $view = str_replace($marker, $extra . $marker, $row->definition);
        DB::statement('DROP VIEW public_prediction_models');
        DB::statement('CREATE VIEW public_prediction_models AS ' . $view);
    }

    private function removeViewColumns(): void
    {
        $row = DB::selectOne(
            "SELECT pg_get_viewdef('public_prediction_models'::regclass, true) AS definition"
        );
        $pattern = '/,\n    p\.feature_drift_status,\n'
            . '    p\.feature_drift_max_zscore,\n'
            . '    p\.feature_drift_ratio,\n'
            . '    p\.feature_drift_veto_used,\n'
            . '    p\.feature_drift_details/';
        $view = preg_replace($pattern, '', $row->definition, 1);
        if ($view === null || $view === $row->definition) {
            throw new RuntimeException('Feature drift columns not found in public view');
        }
        DB::statement('DROP VIEW public_prediction_models');
        DB::statement('CREATE VIEW public_prediction_models AS ' . $view);
    }
};
