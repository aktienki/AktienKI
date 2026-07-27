<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_model_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('ai_type', 20);
            $table->string('timeframe', 10);
            $table->unsignedInteger('prediction_horizon_minutes');
            $table->string('selection_mode', 20);
            $table->string('preferred_model_name', 160)->nullable();
            $table->foreignId('trained_model_id')
                ->nullable()
                ->constrained('trained_models')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(
                ['user_id', 'instrument_id', 'ai_type', 'timeframe',
                 'prediction_horizon_minutes'],
                'user_model_preferences_scope_unique'
            );
            $table->index(
                ['instrument_id', 'ai_type', 'timeframe',
                 'prediction_horizon_minutes'],
                'user_model_preferences_lookup_idx'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE user_model_preferences
            ADD CONSTRAINT user_model_preferences_mode_check
            CHECK (
                (selection_mode = 'family'
                 AND preferred_model_name IS NOT NULL
                 AND trained_model_id IS NULL)
                OR
                (selection_mode = 'version'
                 AND preferred_model_name IS NULL
                 AND trained_model_id IS NOT NULL)
                OR
                (selection_mode = 'champion'
                 AND preferred_model_name IS NULL
                 AND trained_model_id IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_model_preferences');
    }
};
