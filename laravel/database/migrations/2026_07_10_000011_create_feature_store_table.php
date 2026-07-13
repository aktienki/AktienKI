<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feature_store', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('interval',10)->index();
            $table->timestampTz('bar_time')->index();

            $table->decimal('close',24,10);
            $table->decimal('volume',28,4)->nullable();

            $table->decimal('rsi_14',16,10)->nullable();
            $table->decimal('ema_20',24,10)->nullable();
            $table->decimal('ema_50',24,10)->nullable();
            $table->decimal('ema_200',24,10)->nullable();
            $table->decimal('macd',24,10)->nullable();
            $table->decimal('atr_14',24,10)->nullable();
            $table->decimal('volatility_20',16,10)->nullable();

            $table->decimal('target_return_1d',16,10)->nullable();
            $table->decimal('target_return_5d',16,10)->nullable();
            $table->decimal('target_return_20d',16,10)->nullable();
            $table->smallInteger('target_direction')->nullable();

            $table->string('feature_version',20)->default('1.0.0');
            $table->timestampsTz();

            $table->unique(['instrument_id','interval','bar_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_store');
    }
};
