<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chartview_instrument_signal_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->unsignedTinyInteger('lookback_years')->default(3);
            $table->unsignedSmallInteger('horizon_days')->default(20);
            $table->unsignedInteger('sample_size')->default(0);
            $table->unsignedInteger('rising_count')->default(0);
            $table->decimal('rise_probability', 8, 4)->nullable();
            $table->decimal('average_return', 12, 6)->nullable();
            $table->timestampTz('calculated_at');
            $table->timestampsTz();
            $table->unique(['instrument_id', 'event_key'], 'chartview_instrument_signal_unique');
            $table->index(['event_key', 'sample_size'], 'chartview_instrument_signal_samples');
        });

        Schema::table('chartview_signal_events', function (Blueprint $table): void {
            $table->decimal('global_probability', 8, 4)->nullable()->after('rise_probability');
            $table->decimal('instrument_probability', 8, 4)->nullable()->after('global_probability');
            $table->string('probability_scope', 16)->default('global')->after('instrument_probability');
        });
    }

    public function down(): void
    {
        Schema::table('chartview_signal_events', function (Blueprint $table): void {
            $table->dropColumn(['global_probability', 'instrument_probability', 'probability_scope']);
        });
        Schema::dropIfExists('chartview_instrument_signal_statistics');
    }
};
