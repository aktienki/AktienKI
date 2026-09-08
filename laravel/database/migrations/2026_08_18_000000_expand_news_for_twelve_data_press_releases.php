<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->string('provider', 40)->nullable()->after('source');
            $table->string('provider_id', 190)->nullable()->after('provider');
            $table->longText('body')->nullable()->after('summary');
            $table->string('language', 20)->nullable()->after('body');
            $table->text('ai_summary_de')->nullable()->after('language');
            $table->text('ai_summary_en')->nullable()->after('ai_summary_de');
            $table->decimal('sentiment_score', 6, 4)->nullable()->after('ai_summary_en');
            $table->unsignedTinyInteger('relevance_score')->nullable()->after('sentiment_score');
            $table->timestampTz('ai_analyzed_at')->nullable()->after('relevance_score');
            $table->jsonb('raw_data')->nullable()->after('ai_analyzed_at');
            $table->unique(['provider', 'provider_id']);
            $table->index(['provider', 'ai_analyzed_at']);
        });

        Schema::create('news_source_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_release_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['instrument_id', 'provider']);
            $table->index(['provider', 'last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_source_sync_states');
        Schema::table('news', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropIndex(['provider', 'ai_analyzed_at']);
            $table->dropColumn([
                'provider', 'provider_id', 'body', 'language', 'ai_summary_de',
                'ai_summary_en', 'sentiment_score', 'relevance_score',
                'ai_analyzed_at', 'raw_data',
            ]);
        });
    }
};
