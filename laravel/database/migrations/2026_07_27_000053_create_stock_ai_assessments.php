<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ai_assessments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prediction_id')->constrained()->cascadeOnDelete();
            $table->date('assessment_date');
            $table->string('model', 100);
            $table->string('recommendation', 8);
            $table->unsignedSmallInteger('confidence');
            $table->text('summary');
            $table->jsonb('opportunities')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('risks')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('key_factors')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('input_snapshot');
            $table->jsonb('raw_response');
            $table->timestampsTz();
            $table->unique(
                ['instrument_id', 'assessment_date'],
                'stock_ai_assessments_instrument_day_unique'
            );
            $table->index('prediction_id');
        });
        DB::statement(
            "ALTER TABLE stock_ai_assessments
             ADD CONSTRAINT stock_ai_assessments_recommendation_check
             CHECK (recommendation IN ('BUY', 'HOLD', 'SELL')),
             ADD CONSTRAINT stock_ai_assessments_confidence_check
             CHECK (confidence BETWEEN 0 AND 100)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ai_assessments');
    }
};
