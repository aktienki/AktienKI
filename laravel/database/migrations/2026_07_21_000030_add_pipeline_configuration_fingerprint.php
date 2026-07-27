<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_runs', function (Blueprint $table): void {
            $table->char('configuration_hash', 64)->nullable()->index();
        });
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->char('configuration_hash', 64)->nullable()->index();
        });
        Schema::table('predictions', function (Blueprint $table): void {
            $table->char('configuration_hash', 64)->nullable()->index();
        });
        foreach (['training_runs', 'trained_models', 'predictions'] as $table) {
            DB::statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$table}_configuration_hash_chk "
                . "CHECK (configuration_hash IS NULL OR configuration_hash ~ '^[0-9a-f]{64}$')"
            );
        }
        $this->rewriteView(true);
    }

    public function down(): void
    {
        $this->rewriteView(false);
        foreach (['training_runs', 'trained_models', 'predictions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('configuration_hash');
            });
        }
    }

    private function rewriteView(bool $append): void
    {
        $row = DB::selectOne(
            "SELECT pg_get_viewdef('public_prediction_models'::regclass, true) AS definition"
        );
        $marker = "\n   FROM predictions p";
        $extra = ",\n    p.configuration_hash";
        if ($append) {
            if (!str_contains($row->definition, $marker)) {
                throw new RuntimeException('Unexpected public prediction view');
            }
            $view = str_replace($marker, $extra . $marker, $row->definition);
        } else {
            $view = str_replace($extra, '', $row->definition, $count);
            if ($count !== 1) {
                throw new RuntimeException('Configuration hash not found');
            }
        }
        DB::statement('DROP VIEW public_prediction_models');
        DB::statement('CREATE VIEW public_prediction_models AS ' . $view);
    }
};
