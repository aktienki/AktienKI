<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_models', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('sector_key', 120);
            $table->string('ai_type', 30);
            $table->string('timeframe', 20);
            $table->unsignedInteger('prediction_horizon_minutes');
            $table->string('version', 100);
            $table->string('artifact_path', 500);
            $table->timestampTz('trained_at');
            $table->jsonb('metrics');
            $table->jsonb('fold_metrics');
            $table->jsonb('member_instrument_ids');
            $table->boolean('eligible')->default(false);
            $table->string('status', 30)->default('candidate');
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['sector_key', 'ai_type', 'timeframe',
                 'prediction_horizon_minutes', 'version'],
                'sector_models_version_unique'
            );
            $table->index(
                ['sector_key', 'ai_type', 'timeframe',
                 'prediction_horizon_minutes', 'status', 'eligible'],
                'sector_models_active_lookup_idx'
            );
        });

        Schema::create('instrument_model_routes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('instrument_id')
                ->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('sector_model_id')->nullable()
                ->constrained('sector_models')->nullOnDelete();
            $table->string('ai_type', 30);
            $table->string('timeframe', 20);
            $table->unsignedInteger('prediction_horizon_minutes');
            $table->string('selected_scope', 20);
            $table->boolean('eligible')->default(false);
            $table->string('reason', 100);
            $table->jsonb('metrics');
            $table->timestampTz('evaluated_at');
            $table->timestampsTz();

            $table->unique(
                ['instrument_id', 'ai_type', 'timeframe',
                 'prediction_horizon_minutes'],
                'instrument_model_routes_scope_unique'
            );
            $table->index(
                ['selected_scope', 'eligible'],
                'instrument_model_routes_selection_idx'
            );
        });

        Schema::table('predictions', function (Blueprint $table): void {
            $table->foreignId('sector_model_id')->nullable()
                ->constrained('sector_models')->nullOnDelete();
            $table->string('model_scope_source', 20)->default('instrument');
            $table->index(
                ['model_scope_source', 'sector_model_id'],
                'predictions_sector_source_idx'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE instrument_model_routes
            ADD CONSTRAINT instrument_model_routes_scope_check
            CHECK (selected_scope IN ('instrument', 'sector', 'rejected'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE predictions
            ADD CONSTRAINT predictions_model_scope_source_check
            CHECK (model_scope_source IN ('instrument', 'sector'))
            SQL);
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropIndex('predictions_sector_source_idx');
            $table->dropConstrainedForeignId('sector_model_id');
            $table->dropColumn('model_scope_source');
        });
        Schema::dropIfExists('instrument_model_routes');
        Schema::dropIfExists('sector_models');
    }
};
