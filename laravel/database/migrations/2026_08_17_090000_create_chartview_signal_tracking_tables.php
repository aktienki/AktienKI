<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chartview_signal_statistics', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->string('label_de', 120);
            $table->string('label_en', 120);
            $table->string('tone', 16);
            $table->unsignedTinyInteger('lookback_years')->default(3);
            $table->unsignedSmallInteger('horizon_days')->default(20);
            $table->unsignedInteger('sample_size')->default(0);
            $table->unsignedInteger('rising_count')->default(0);
            $table->decimal('rise_probability', 8, 4)->nullable();
            $table->decimal('average_return', 12, 6)->nullable();
            $table->timestampTz('calculated_at');
            $table->timestampsTz();
        });

        Schema::create('chartview_signal_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->timestampTz('bar_time')->index();
            $table->string('event_key', 64)->index();
            $table->string('tone', 16);
            $table->decimal('rise_probability', 8, 4)->nullable();
            $table->unsignedInteger('sample_size')->default(0);
            $table->timestampsTz();
            $table->unique(['instrument_id', 'bar_time', 'event_key'], 'chartview_events_unique');
            $table->index(['bar_time', 'rise_probability'], 'chartview_events_recent_probability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chartview_signal_events');
        Schema::dropIfExists('chartview_signal_statistics');
    }
};
