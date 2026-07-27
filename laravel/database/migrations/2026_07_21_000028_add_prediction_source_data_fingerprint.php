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
            $table->char('source_data_hash', 64)->nullable();
            $table->index('source_data_hash', 'predictions_source_data_hash_idx');
        });
        $this->rewriteView(true);
    }

    public function down(): void
    {
        $this->rewriteView(false);
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_source_data_hash_idx');
            $table->dropColumn('source_data_hash');
        });
    }

    private function rewriteView(bool $append): void
    {
        $row = DB::selectOne(
            "SELECT pg_get_viewdef('public_prediction_models'::regclass, true) AS definition"
        );
        $marker = "\n   FROM predictions p";
        $extra = ",\n    p.source_data_hash";
        if ($append) {
            if (!str_contains($row->definition, $marker)) {
                throw new RuntimeException('Unexpected public prediction view');
            }
            $view = str_replace($marker, $extra . $marker, $row->definition);
        } else {
            $view = str_replace($extra, '', $row->definition, $count);
            if ($count !== 1) {
                throw new RuntimeException('Source hash column not found');
            }
        }
        DB::statement('DROP VIEW public_prediction_models');
        DB::statement('CREATE VIEW public_prediction_models AS ' . $view);
    }
};
