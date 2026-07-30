<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_market_ai_analyses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->date('analysis_date')->unique();
            $table->string('model', 100);
            $table->string('market_outlook', 10);
            $table->unsignedSmallInteger('confidence');
            $table->string('risk_level', 10);
            $table->string('headline', 500);
            $table->text('executive_summary');
            $table->text('breadth_analysis');
            $table->jsonb('sector_analysis')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('opportunities')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('risks')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('watchlist')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('input_snapshot');
            $table->jsonb('raw_response');
            $table->timestampsTz();
        });
        DB::statement(
            "ALTER TABLE daily_market_ai_analyses
             ADD CONSTRAINT daily_market_ai_analyses_outlook_check
             CHECK (market_outlook IN ('BULLISH', 'NEUTRAL', 'BEARISH')),
             ADD CONSTRAINT daily_market_ai_analyses_risk_check
             CHECK (risk_level IN ('LOW', 'MEDIUM', 'HIGH')),
             ADD CONSTRAINT daily_market_ai_analyses_confidence_check
             CHECK (confidence BETWEEN 0 AND 100)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_market_ai_analyses');
    }
};
