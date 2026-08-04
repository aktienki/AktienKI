<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_selection_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tariff_plan_id')->nullable()->constrained('tariff_plans')->nullOnDelete();
            $table->foreignId('backtest_run_id')->nullable()->constrained('backtest_runs')->nullOnDelete();
            $table->string('name', 80);
            $table->string('category', 40)->default('smart_selection');
            $table->json('criteria');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_selection_labels');
    }
};
