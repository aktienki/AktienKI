<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leveraged_product_risk_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->date('signal_date');
            $table->unsignedSmallInteger('horizon_days')->default(20);
            $table->string('position_side', 8);
            $table->decimal('predicted_return', 14, 8);
            $table->decimal('point_in_time_score', 7, 4);
            $table->unsignedTinyInteger('score_bucket');
            $table->decimal('volatility', 14, 8)->nullable();
            $table->string('volatility_bucket', 12)->nullable();
            $table->decimal('realized_return', 14, 8);
            $table->decimal('maximum_adverse_excursion', 14, 8);
            $table->decimal('maximum_favorable_excursion', 14, 8);
            $table->jsonb('barrier_breaches');
            $table->string('calculation_version', 80);
            $table->timestampsTz();

            $table->unique(
                ['instrument_id', 'signal_date', 'horizon_days', 'position_side'],
                'leveraged_risk_observation_unique'
            );
            $table->index(['instrument_id', 'position_side', 'score_bucket'], 'leveraged_risk_observation_lookup');
        });

        Schema::create('leveraged_product_risk_matrices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->unsignedSmallInteger('horizon_days')->default(20);
            $table->string('position_side', 8);
            $table->unsignedTinyInteger('score_bucket');
            $table->string('volatility_bucket', 12)->nullable();
            $table->unsignedSmallInteger('barrier_distance_bps');
            $table->unsignedInteger('sample_size');
            $table->unsignedInteger('loss_count');
            $table->unsignedInteger('barrier_breach_count');
            $table->decimal('loss_probability', 9, 6);
            $table->decimal('barrier_breach_probability', 9, 6);
            $table->decimal('average_return', 14, 8);
            $table->decimal('expected_shortfall_10', 14, 8);
            $table->decimal('average_adverse_excursion', 14, 8);
            $table->boolean('sample_size_sufficient')->default(false);
            $table->string('calculation_version', 80);
            $table->timestampTz('calculated_at');
            $table->timestampsTz();

            $table->unique(
                ['instrument_id', 'horizon_days', 'position_side', 'score_bucket', 'volatility_bucket', 'barrier_distance_bps'],
                'leveraged_risk_matrix_unique'
            );
            $table->index(['instrument_id', 'position_side', 'horizon_days'], 'leveraged_risk_matrix_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leveraged_product_risk_matrices');
        Schema::dropIfExists('leveraged_product_risk_observations');
    }
};
