<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_screening_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('universe', 32)->default('top100');
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 12, 6)->nullable();
            $table->jsonb('parameters')->nullable();
            $table->timestampTz('generated_at')->index();
            $table->timestampsTz();
        });

        Schema::create('stock_screening_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('screening_run_id')->constrained('stock_screening_runs')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->decimal('ranking_score', 12, 6)->nullable();
            $table->string('signal', 16)->nullable();
            $table->text('comment_de')->nullable();
            $table->text('comment_en')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->timestampsTz();
            $table->unique(['screening_run_id', 'instrument_id']);
            $table->index(['screening_run_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_screening_items');
        Schema::dropIfExists('stock_screening_runs');
    }
};
