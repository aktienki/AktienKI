<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_validations', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->foreignId('prediction_id')
                ->constrained('predictions')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('validation_horizon_days');
            $table->timestampTz('target_time')->index();

            $table->decimal('actual_price', 24, 10);
            $table->decimal('actual_market_return', 16, 10);

            $table->decimal('actual_long_return', 16, 10);
            $table->decimal('actual_short_return', 16, 10);
            $table->decimal('actual_strategy_return', 16, 10);

            $table->decimal('prediction_error', 24, 10);
            $table->decimal('prediction_error_pct', 16, 10);

            $table->boolean('direction_correct')->nullable();
            $table->boolean('strategy_correct')->nullable();
            $table->boolean('target_hit')->nullable();

            $table->decimal('future_high', 24, 10)->nullable();
            $table->decimal('future_low', 24, 10)->nullable();

            $table->decimal('max_favorable_excursion', 16, 10)->nullable();
            $table->decimal('max_adverse_excursion', 16, 10)->nullable();

            $table->timestampTz('validated_at');
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->unique(
                ['prediction_id', 'validation_horizon_days'],
                'prediction_validations_prediction_horizon_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_validations');
    }
};
