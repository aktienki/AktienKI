<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->foreignId('instrument_id')
                ->constrained('instruments')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('trained_model_id')->nullable()->index();
            $table->timestampTz('prediction_time')->index();
            $table->string('interval', 10)->default('1d')->index();

            $table->decimal('current_price', 24, 10);

            $table->decimal('predicted_price_5d', 24, 10)->nullable();
            $table->decimal('predicted_price_10d', 24, 10)->nullable();
            $table->decimal('predicted_price_20d', 24, 10)->nullable();

            $table->decimal('price_difference_5d', 24, 10)->nullable();
            $table->decimal('price_difference_10d', 24, 10)->nullable();
            $table->decimal('price_difference_20d', 24, 10)->nullable();

            $table->decimal('market_return_5d', 16, 10)->nullable();
            $table->decimal('market_return_10d', 16, 10)->nullable();
            $table->decimal('market_return_20d', 16, 10)->nullable();

            $table->decimal('long_return_5d', 16, 10)->nullable();
            $table->decimal('long_return_10d', 16, 10)->nullable();
            $table->decimal('long_return_20d', 16, 10)->nullable();

            $table->decimal('short_return_5d', 16, 10)->nullable();
            $table->decimal('short_return_10d', 16, 10)->nullable();
            $table->decimal('short_return_20d', 16, 10)->nullable();

            $table->string('strategy', 16)->index();
            $table->decimal('strategy_return_5d', 16, 10)->nullable();
            $table->decimal('strategy_return_10d', 16, 10)->nullable();
            $table->decimal('strategy_return_20d', 16, 10)->nullable();

            $table->decimal('direction_score', 8, 4)->nullable();
            $table->decimal('signal_strength', 8, 4)->nullable();
            $table->decimal('confidence', 8, 4)->nullable();
            $table->decimal('risk_score', 8, 4)->nullable();
            $table->decimal('trend_strength', 8, 4)->nullable();
            $table->decimal('ai_score', 8, 4)->nullable();

            $table->string('signal', 32)->index();
            $table->string('status', 24)->default('pending_validation')->index();

            $table->jsonb('explanation')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->index(
                ['instrument_id', 'prediction_time'],
                'predictions_instrument_time_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
