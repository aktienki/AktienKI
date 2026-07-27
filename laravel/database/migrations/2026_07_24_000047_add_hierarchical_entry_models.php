<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_scope_models', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('scope_type', 20);
            $table->string('scope_key', 160);
            $table->string('benchmark_symbol', 40)->nullable();
            $table->string('ai_type', 30);
            $table->string('timeframe', 20);
            $table->unsignedInteger('prediction_horizon_minutes');
            $table->string('version', 100);
            $table->string('artifact_path', 500);
            $table->timestampTz('trained_at');
            $table->jsonb('metrics');
            $table->jsonb('fold_metrics');
            $table->jsonb('member_instrument_ids');
            $table->jsonb('component_model_ids')->default('[]');
            $table->boolean('eligible')->default(false);
            $table->string('status', 30)->default('candidate');
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['scope_type', 'scope_key', 'ai_type', 'timeframe',
                 'prediction_horizon_minutes', 'version'],
                'entry_scope_models_version_unique'
            );
            $table->index(
                ['scope_type', 'scope_key', 'ai_type', 'timeframe',
                 'prediction_horizon_minutes', 'status', 'eligible'],
                'entry_scope_models_active_lookup_idx'
            );
        });

        Schema::create('instrument_entry_model_routes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('instrument_id')
                ->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('individual_model_id')->nullable()
                ->constrained('trained_models')->nullOnDelete();
            foreach (['sector', 'index', 'combined'] as $scope) {
                $table->foreignId($scope.'_model_id')->nullable()
                    ->constrained('entry_scope_models')->nullOnDelete();
            }
            $table->string('ai_type', 30);
            $table->string('timeframe', 20);
            $table->unsignedInteger('prediction_horizon_minutes');
            $table->string('selected_scope', 20);
            $table->boolean('eligible')->default(false);
            $table->string('reason', 120);
            $table->decimal('score', 8, 6)->default(0);
            $table->jsonb('candidate_scores');
            $table->timestampTz('evaluated_at');
            $table->timestampsTz();
            $table->unique(
                ['instrument_id', 'ai_type', 'timeframe',
                 'prediction_horizon_minutes'],
                'instrument_entry_routes_scope_unique'
            );
        });

        Schema::table('predictions', function (Blueprint $table): void {
            $table->foreignId('entry_scope_model_id')->nullable()
                ->constrained('entry_scope_models')->nullOnDelete();
            $table->string('entry_model_scope_source', 20)
                ->default('individual');
            $table->jsonb('entry_scope_scores')->default('{}');
        });
        DB::statement("ALTER TABLE entry_scope_models ADD CONSTRAINT entry_scope_models_type_check CHECK (scope_type IN ('sector','index','combined'))");
        DB::statement("ALTER TABLE instrument_entry_model_routes ADD CONSTRAINT instrument_entry_routes_scope_check CHECK (selected_scope IN ('individual','sector','index','combined','rejected'))");
        DB::statement("ALTER TABLE predictions ADD CONSTRAINT predictions_entry_scope_check CHECK (entry_model_scope_source IN ('individual','sector','index','combined'))");
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('entry_scope_model_id');
            $table->dropColumn(['entry_model_scope_source', 'entry_scope_scores']);
        });
        Schema::dropIfExists('instrument_entry_model_routes');
        Schema::dropIfExists('entry_scope_models');
    }
};
