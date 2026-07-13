<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_experiments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('strategy_profile_id')
                ->constrained('strategy_profiles')
                ->cascadeOnDelete();

            $table->foreignId('instrument_id')
                ->constrained('instruments')
                ->cascadeOnDelete();

            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('status', 24)->default('pending')->index();

            $table->jsonb('search_space');
            $table->jsonb('algorithms');
            $table->jsonb('selection_rules')->nullable();

            $table->unsignedInteger('variants_total')->default(0);
            $table->unsignedInteger('variants_completed')->default(0);
            $table->unsignedInteger('variants_failed')->default(0);

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();

            $table->text('error_message')->nullable();
            $table->timestampsTz();
        });

        Schema::create('strategy_experiment_variants', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('strategy_experiment_id')
                ->constrained('strategy_experiments')
                ->cascadeOnDelete();

            $table->string('variant_code', 100);
            $table->string('status', 24)->default('pending')->index();

            $table->jsonb('resolved_configuration');
            $table->string('configuration_hash', 64)->index();

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();

            $table->text('error_message')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['strategy_experiment_id', 'variant_code'],
                'strategy_experiment_variants_code_unique'
            );
        });

        Schema::create('strategy_experiment_results', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('strategy_experiment_variant_id')
                ->constrained('strategy_experiment_variants')
                ->cascadeOnDelete();

            $table->foreignId('trained_model_id')
                ->nullable()
                ->constrained('trained_models')
                ->nullOnDelete();

            $table->string('algorithm', 32)->index();
            $table->string('status', 24)->default('completed')->index();

            $table->decimal('validation_mae', 18, 10)->nullable();
            $table->decimal('validation_rmse', 18, 10)->nullable();
            $table->decimal('validation_r2', 18, 10)->nullable();
            $table->decimal('validation_direction_accuracy', 10, 6)->nullable();

            $table->decimal('test_mae', 18, 10)->nullable();
            $table->decimal('test_rmse', 18, 10)->nullable();
            $table->decimal('test_r2', 18, 10)->nullable();
            $table->decimal('test_direction_accuracy', 10, 6)->nullable();

            $table->decimal('stability_score', 10, 6)->nullable();
            $table->decimal('selection_score', 10, 6)->nullable();

            $table->jsonb('metrics')->nullable();
            $table->jsonb('feature_importance')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->unique(
                ['strategy_experiment_variant_id', 'algorithm'],
                'strategy_experiment_results_variant_algorithm_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_experiment_results');
        Schema::dropIfExists('strategy_experiment_variants');
        Schema::dropIfExists('strategy_experiments');
    }
};
