<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_strategy_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saved_prediction_filter_id')->constrained('saved_prediction_filters')->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prediction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->decimal('reserved_capital', 20, 6);
            $table->unsignedSmallInteger('position_factor')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestampTz('expires_at');
            $table->timestampTz('released_at')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestampsTz();

            $table->unique(['saved_prediction_filter_id', 'prediction_id'], 'portfolio_strategy_reservation_prediction_uq');
            $table->index(['portfolio_id', 'status', 'expires_at'], 'portfolio_strategy_reservation_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_strategy_reservations');
    }
};
