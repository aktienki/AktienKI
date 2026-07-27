<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_scope_models', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('scope_type', 20);
            $table->string('scope_key', 120);
            $table->string('ai_type', 32)->default('exit');
            $table->string('timeframe', 16)->default('1d');
            $table->string('version', 100);
            $table->string('artifact_path', 500);
            $table->jsonb('metrics');
            $table->jsonb('fold_metrics');
            $table->jsonb('member_instrument_ids');
            $table->boolean('eligible')->default(false);
            $table->string('status', 30)->default('candidate');
            $table->timestampTz('trained_at');
            $table->timestampsTz();
            $table->unique(
                ['scope_type', 'scope_key', 'ai_type', 'timeframe', 'version'],
                'exit_scope_models_version_unique'
            );
            $table->index(
                ['scope_type', 'scope_key', 'status', 'eligible'],
                'exit_scope_models_active_lookup_idx'
            );
        });

        Schema::create('instrument_exit_model_routes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('instrument_id')
                ->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('sector_exit_model_id')->nullable()
                ->constrained('exit_scope_models')->nullOnDelete();
            $table->foreignId('index_exit_model_id')->nullable()
                ->constrained('exit_scope_models')->nullOnDelete();
            $table->string('ai_type', 32)->default('exit');
            $table->string('timeframe', 16)->default('1d');
            $table->string('selected_scope', 24);
            $table->string('benchmark_symbol', 32)->nullable();
            $table->decimal('individual_weight', 8, 6)->default(1);
            $table->decimal('sector_weight', 8, 6)->default(0);
            $table->decimal('index_weight', 8, 6)->default(0);
            $table->boolean('eligible')->default(false);
            $table->string('reason', 120);
            $table->jsonb('metrics');
            $table->jsonb('fold_metrics');
            $table->string('routing_version', 40);
            $table->timestampTz('evaluated_at');
            $table->timestampsTz();
            $table->unique(
                ['instrument_id', 'ai_type', 'timeframe'],
                'instrument_exit_model_routes_scope_unique'
            );
            $table->index(
                ['selected_scope', 'eligible'],
                'instrument_exit_model_routes_selection_idx'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE exit_scope_models
            ADD CONSTRAINT exit_scope_models_type_check
            CHECK (scope_type IN ('sector', 'index'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE instrument_exit_model_routes
            ADD CONSTRAINT instrument_exit_model_routes_scope_check
            CHECK (selected_scope IN (
                'individual', 'sector', 'index', 'combined', 'safety_fallback'
            ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE instrument_exit_model_routes
            ADD CONSTRAINT instrument_exit_model_routes_weights_check
            CHECK (
                individual_weight >= 0 AND sector_weight >= 0 AND index_weight >= 0
                AND abs(individual_weight + sector_weight + index_weight - 1) < 0.000001
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_exit_model_routes');
        Schema::dropIfExists('exit_scope_models');
    }
};
