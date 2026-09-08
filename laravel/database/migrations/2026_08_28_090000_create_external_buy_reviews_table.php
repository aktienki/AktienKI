<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_buy_reviews', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prediction_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('previous_prediction_id')->nullable()->constrained('predictions')->nullOnDelete();

            $table->timestampTz('triggered_at')->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('provider', 32)->default('openai');
            $table->string('model', 100);
            $table->string('prompt_version', 64);
            $table->string('model_verdict', 32)->nullable();
            $table->string('verdict', 32)->nullable()->index();
            $table->unsignedSmallInteger('confidence')->nullable();
            $table->text('summary')->nullable();

            $table->jsonb('positive_factors')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('risk_factors')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('key_findings')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('research_limitations')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('sources')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('request_identity');
            $table->jsonb('signal_scope');
            $table->jsonb('usage')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('pricing_snapshot')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('raw_response')->nullable();

            $table->string('provider_response_id')->nullable()->index();
            $table->unsignedSmallInteger('search_call_count')->default(0);
            $table->unsignedBigInteger('estimated_cost_microusd')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('researched_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->index(['instrument_id', 'triggered_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE external_buy_reviews
            ADD CONSTRAINT external_buy_reviews_status_check
                CHECK (status IN ('pending', 'running', 'completed', 'failed')),
            ADD CONSTRAINT external_buy_reviews_model_verdict_check
                CHECK (model_verdict IS NULL OR model_verdict IN ('NO_OBJECTION', 'CAUTION', 'OBJECTION', 'INSUFFICIENT_EVIDENCE')),
            ADD CONSTRAINT external_buy_reviews_verdict_check
                CHECK (verdict IS NULL OR verdict IN ('NO_OBJECTION', 'CAUTION', 'OBJECTION', 'INSUFFICIENT_EVIDENCE')),
            ADD CONSTRAINT external_buy_reviews_confidence_check
                CHECK (confidence IS NULL OR confidence BETWEEN 0 AND 100)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('external_buy_reviews');
    }
};
