<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earnings_price_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('corporate_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('event_date');
            $table->decimal('surprise_percent', 14, 6)->nullable();
            // Trading-day-indexed closing price relative to the event -
            // not calendar days, since price_bars only has trading days.
            $table->decimal('close_at_event', 18, 6)->nullable();
            $table->decimal('return_pre_5d', 14, 6)->nullable();
            $table->decimal('return_1d', 14, 6)->nullable();
            $table->decimal('return_5d', 14, 6)->nullable();
            $table->decimal('return_10d', 14, 6)->nullable();
            $table->decimal('return_20d', 14, 6)->nullable();
            $table->decimal('return_40d', 14, 6)->nullable();
            // Null while the event doesn't yet have enough trading days of
            // forward price history to fill every horizon above.
            $table->boolean('is_complete')->default(false)->index();
            $table->timestampTz('computed_at')->index();
            $table->timestampsTz();
            $table->unique('corporate_event_id');
            $table->index(['instrument_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earnings_price_reactions');
    }
};
