<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_top_stock_selections', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->date('selection_date');
            $table->unsignedSmallInteger('rank');
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('prediction_id')->constrained('predictions')->cascadeOnDelete();
            $table->decimal('recommendation_score', 8, 4);
            $table->decimal('prediction_score_percent', 8, 4);
            $table->decimal('confidence_percent', 8, 4);
            $table->decimal('risk_percent', 8, 4);
            $table->decimal('expected_return_percent', 12, 6);
            $table->jsonb('selection_details');
            $table->timestampsTz();

            $table->unique(['selection_date', 'rank']);
            $table->unique(['selection_date', 'instrument_id']);
            $table->index(['selection_date', 'recommendation_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_top_stock_selections');
    }
};
