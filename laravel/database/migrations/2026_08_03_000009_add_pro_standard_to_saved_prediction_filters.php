<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_prediction_filters', function (Blueprint $table): void {
            $table->string('visibility', 24)->default('private')->index();
            $table->text('description')->nullable();
            $table->foreignId('source_strategy_id')->nullable()->constrained('saved_prediction_filters')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('saved_prediction_filters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_strategy_id');
            $table->dropColumn(['visibility', 'description', 'published_at']);
        });
    }
};
