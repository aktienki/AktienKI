<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_context_predictions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->date('prediction_date');
            $table->string('scope_type', 16);
            $table->string('scope_key', 160);
            $table->decimal('score', 8, 4);
            $table->decimal('confidence', 8, 4)->nullable();
            $table->string('signal', 16)->default('HOLD');
            $table->unsignedInteger('member_count');
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->unique(['prediction_date', 'scope_type', 'scope_key'], 'market_context_prediction_unique');
            $table->index(['scope_type', 'scope_key', 'prediction_date'], 'market_context_prediction_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_context_predictions');
    }
};
