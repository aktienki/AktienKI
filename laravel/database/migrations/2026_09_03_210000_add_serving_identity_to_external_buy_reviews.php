<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_buy_reviews', function (Blueprint $table): void {
            $table->foreignId('prediction_id')->nullable()->change();
            $table->unsignedBigInteger('serving_instrument_id')->nullable()->after('prediction_id');
            $table->uuid('serving_batch_id')->nullable()->after('serving_instrument_id');
            $table->unique(['serving_instrument_id', 'serving_batch_id'], 'external_buy_reviews_serving_scope_unique');
        });

        Schema::create('external_buy_review_signal_states', function (Blueprint $table): void {
            $table->unsignedBigInteger('serving_instrument_id')->primary();
            $table->string('last_signal', 16);
            $table->uuid('last_batch_id');
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_buy_review_signal_states');
        Schema::table('external_buy_reviews', function (Blueprint $table): void {
            $table->dropUnique('external_buy_reviews_serving_scope_unique');
            $table->dropColumn(['serving_instrument_id', 'serving_batch_id']);
        });
    }
};
