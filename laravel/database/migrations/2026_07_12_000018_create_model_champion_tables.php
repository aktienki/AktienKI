<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_champions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('strategy_profile_id')
                ->constrained('strategy_profiles')
                ->cascadeOnDelete();

            $table->foreignId('instrument_id')
                ->nullable()
                ->constrained('instruments')
                ->cascadeOnDelete();

            $table->foreignId('active_trained_model_id')
                ->constrained('trained_models')
                ->restrictOnDelete();

            $table->foreignId('previous_trained_model_id')
                ->nullable()
                ->constrained('trained_models')
                ->nullOnDelete();

            $table->string('algorithm', 32)->index();
            $table->string('status', 24)->default('active')->index();

            $table->decimal('elo_rating', 12, 4)->default(1500);
            $table->unsignedBigInteger('validated_predictions_count')
                ->default(0);

            $table->decimal('direction_accuracy', 10, 6)->nullable();
            $table->decimal('average_strategy_return', 18, 10)->nullable();
            $table->decimal('rmse', 18, 10)->nullable();
            $table->decimal('stability_score', 10, 6)->nullable();

            $table->timestampTz('activated_at');
            $table->text('activation_reason')->nullable();
            $table->jsonb('activation_metrics')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->unique(
                ['strategy_profile_id', 'instrument_id'],
                'model_champions_strategy_instrument_unique'
            );
        });

        Schema::create('model_challengers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('strategy_profile_id')
                ->constrained('strategy_profiles')
                ->cascadeOnDelete();

            $table->foreignId('instrument_id')
                ->nullable()
                ->constrained('instruments')
                ->cascadeOnDelete();

            $table->foreignId('trained_model_id')
                ->constrained('trained_models')
                ->cascadeOnDelete();

            $table->foreignId('champion_model_id')
                ->nullable()
                ->constrained('trained_models')
                ->nullOnDelete();

            $table->string('algorithm', 32)->index();
            $table->string('status', 24)->default('evaluating')->index();

            $table->decimal('elo_rating', 12, 4)->default(1500);
            $table->unsignedBigInteger('validated_predictions_count')
                ->default(0);

            $table->decimal('direction_accuracy', 10, 6)->nullable();
            $table->decimal('average_strategy_return', 18, 10)->nullable();
            $table->decimal('rmse', 18, 10)->nullable();
            $table->decimal('stability_score', 10, 6)->nullable();

            $table->timestampTz('evaluation_started_at');
            $table->timestampTz('evaluation_finished_at')->nullable();

            $table->text('status_reason')->nullable();
            $table->jsonb('evaluation_metrics')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->unique(
                ['strategy_profile_id', 'instrument_id', 'trained_model_id'],
                'model_challengers_strategy_instrument_model_unique'
            );
        });

        Schema::create('model_comparisons', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('strategy_profile_id')
                ->constrained('strategy_profiles')
                ->cascadeOnDelete();

            $table->foreignId('instrument_id')
                ->nullable()
                ->constrained('instruments')
                ->cascadeOnDelete();

            $table->foreignId('champion_model_id')
                ->constrained('trained_models')
                ->cascadeOnDelete();

            $table->foreignId('challenger_model_id')
                ->constrained('trained_models')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('prediction_count')->default(0);

            $table->decimal('champion_direction_accuracy', 10, 6)->nullable();
            $table->decimal('challenger_direction_accuracy', 10, 6)->nullable();

            $table->decimal('champion_strategy_return', 18, 10)->nullable();
            $table->decimal('challenger_strategy_return', 18, 10)->nullable();

            $table->decimal('champion_rmse', 18, 10)->nullable();
            $table->decimal('challenger_rmse', 18, 10)->nullable();

            $table->decimal('champion_stability_score', 10, 6)->nullable();
            $table->decimal('challenger_stability_score', 10, 6)->nullable();

            $table->decimal('champion_selection_score', 10, 6)->nullable();
            $table->decimal('challenger_selection_score', 10, 6)->nullable();

            $table->string('winner', 24)->nullable()->index();
            $table->boolean('promotion_recommended')->default(false)->index();

            $table->timestampTz('compared_at');
            $table->jsonb('comparison_rules')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->index(
                [
                    'strategy_profile_id',
                    'instrument_id',
                    'compared_at',
                ],
                'model_comparisons_strategy_instrument_date_index'
            );
        });

        Schema::create('model_elo_history', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('trained_model_id')
                ->constrained('trained_models')
                ->cascadeOnDelete();

            $table->foreignId('model_comparison_id')
                ->nullable()
                ->constrained('model_comparisons')
                ->nullOnDelete();

            $table->decimal('rating_before', 12, 4);
            $table->decimal('rating_after', 12, 4);
            $table->decimal('rating_change', 12, 4);

            $table->string('result', 16)->index();
            $table->string('opponent_type', 24)->nullable();
            $table->foreignId('opponent_model_id')
                ->nullable()
                ->constrained('trained_models')
                ->nullOnDelete();

            $table->timestampTz('rated_at');
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->index(
                ['trained_model_id', 'rated_at'],
                'model_elo_history_model_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_elo_history');
        Schema::dropIfExists('model_comparisons');
        Schema::dropIfExists('model_challengers');
        Schema::dropIfExists('model_champions');
    }
};
