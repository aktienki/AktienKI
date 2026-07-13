<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_indicators', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->foreignId('instrument_id')
                ->constrained('instruments')
                ->cascadeOnDelete();

            $table->string('interval', 10)->index();
            $table->timestampTz('bar_time')->index();

            $table->decimal('sma_20', 24, 10)->nullable();
            $table->decimal('sma_50', 24, 10)->nullable();
            $table->decimal('sma_200', 24, 10)->nullable();

            $table->decimal('ema_12', 24, 10)->nullable();
            $table->decimal('ema_20', 24, 10)->nullable();
            $table->decimal('ema_26', 24, 10)->nullable();
            $table->decimal('ema_50', 24, 10)->nullable();
            $table->decimal('ema_200', 24, 10)->nullable();

            $table->decimal('rsi_14', 16, 10)->nullable();

            $table->decimal('macd', 24, 10)->nullable();
            $table->decimal('macd_signal', 24, 10)->nullable();
            $table->decimal('macd_histogram', 24, 10)->nullable();

            $table->decimal('atr_14', 24, 10)->nullable();
            $table->decimal('adx_14', 16, 10)->nullable();

            $table->decimal('bollinger_upper', 24, 10)->nullable();
            $table->decimal('bollinger_middle', 24, 10)->nullable();
            $table->decimal('bollinger_lower', 24, 10)->nullable();
            $table->decimal('bollinger_width', 16, 10)->nullable();

            $table->decimal('stochastic_k', 16, 10)->nullable();
            $table->decimal('stochastic_d', 16, 10)->nullable();

            $table->decimal('roc_12', 16, 10)->nullable();
            $table->decimal('momentum_10', 24, 10)->nullable();
            $table->decimal('volatility_20', 16, 10)->nullable();
            $table->decimal('volume_sma_20', 28, 4)->nullable();

            $table->string('calculation_version', 32)->default('1.0.0');
            $table->timestampsTz();

            $table->unique(
                ['instrument_id', 'interval', 'bar_time'],
                'technical_indicators_instrument_interval_time_unique'
            );

            $table->index(
                ['instrument_id', 'interval', 'bar_time'],
                'technical_indicators_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_indicators');
    }
};
