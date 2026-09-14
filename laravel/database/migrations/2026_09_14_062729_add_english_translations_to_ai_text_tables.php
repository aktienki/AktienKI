<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_ai_assessments', function (Blueprint $table): void {
            $table->text('summary_en')->nullable()->after('summary');
            $table->json('opportunities_en')->nullable()->after('opportunities');
            $table->json('risks_en')->nullable()->after('risks');
            $table->json('key_factors_en')->nullable()->after('key_factors');
        });

        Schema::table('external_buy_reviews', function (Blueprint $table): void {
            $table->text('summary_en')->nullable()->after('summary');
            $table->json('positive_factors_en')->nullable()->after('positive_factors');
            $table->json('risk_factors_en')->nullable()->after('risk_factors');
            $table->json('key_findings_en')->nullable()->after('key_findings');
        });
    }

    public function down(): void
    {
        Schema::table('stock_ai_assessments', function (Blueprint $table): void {
            $table->dropColumn(['summary_en', 'opportunities_en', 'risks_en', 'key_factors_en']);
        });

        Schema::table('external_buy_reviews', function (Blueprint $table): void {
            $table->dropColumn(['summary_en', 'positive_factors_en', 'risk_factors_en', 'key_findings_en']);
        });
    }
};
