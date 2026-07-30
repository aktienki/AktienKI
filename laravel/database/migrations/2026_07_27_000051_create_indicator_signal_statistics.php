<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('indicator_signal_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('interval', 16)->default('1d');
            $table->string('indicator', 64);
            $table->string('regime', 32);
            $table->string('position_side', 8);
            $table->unsignedSmallInteger('horizon_days');
            $table->smallInteger('value_bucket');
            $table->decimal('value_lower', 20, 8)->nullable();
            $table->decimal('value_upper', 20, 8)->nullable();
            $table->unsignedInteger('sample_size');
            $table->decimal('mean_return', 14, 8);
            $table->decimal('median_return', 14, 8);
            $table->decimal('hit_rate', 10, 8);
            $table->decimal('wilson_lower', 10, 8);
            $table->decimal('profit_factor', 14, 6);
            $table->decimal('positive_year_ratio', 10, 8);
            $table->decimal('signal_score', 8, 3);
            $table->boolean('eligible')->default(false);
            $table->string('calculation_version', 64);
            $table->timestampsTz();
            $table->unique(
                ['instrument_id', 'interval', 'indicator', 'regime',
                 'position_side', 'horizon_days', 'value_bucket'],
                'indicator_signal_statistics_scope_unique'
            );
            $table->index(
                ['indicator', 'regime', 'position_side', 'horizon_days', 'eligible'],
                'indicator_signal_statistics_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_signal_statistics');
    }
};
