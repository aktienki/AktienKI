<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_analyst_consensuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date')->index();
            $table->decimal('recommendation_score', 8, 5)->nullable();
            $table->unsignedInteger('strong_buy')->nullable();
            $table->unsignedInteger('buy')->nullable();
            $table->unsignedInteger('hold')->nullable();
            $table->unsignedInteger('sell')->nullable();
            $table->unsignedInteger('strong_sell')->nullable();
            $table->decimal('target_high', 20, 6)->nullable();
            $table->decimal('target_median', 20, 6)->nullable();
            $table->decimal('target_low', 20, 6)->nullable();
            $table->decimal('target_average', 20, 6)->nullable();
            $table->decimal('reference_price', 20, 6)->nullable();
            $table->string('currency', 8)->nullable();
            $table->timestampTz('retrieved_at')->index();
            $table->jsonb('raw_data');
            $table->string('source', 32)->default('twelve_data');
            $table->timestampsTz();
            $table->unique(['instrument_id', 'snapshot_date']);
        });

        Schema::create('instrument_analyst_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('rating_date')->index();
            $table->string('firm', 160);
            $table->string('rating_change', 80)->nullable();
            $table->string('rating_current', 120)->nullable();
            $table->string('rating_prior', 120)->nullable();
            $table->timestampTz('retrieved_at')->index();
            $table->jsonb('data');
            $table->string('source', 32)->default('twelve_data');
            $table->timestampsTz();
            $table->unique(['instrument_id', 'rating_date', 'firm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_analyst_ratings');
        Schema::dropIfExists('instrument_analyst_consensuses');
    }
};
