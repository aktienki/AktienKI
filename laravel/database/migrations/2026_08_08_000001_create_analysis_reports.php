<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analysis_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('report_type', 40)->default('signal_change')->index();
            $table->string('symbol', 32)->index();
            $table->string('signal_from', 20)->nullable()->index();
            $table->string('signal_to', 20)->nullable()->index();
            $table->timestampTz('transition_at')->nullable()->index();
            $table->timestampTz('prediction_at')->nullable();
            $table->string('model', 80)->nullable();
            $table->string('status', 24)->default('completed')->index();
            $table->text('report_text')->nullable();
            $table->jsonb('report_data')->nullable();
            $table->string('pdf_path')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 12, 6)->nullable();
            $table->timestampsTz();
            $table->index(['instrument_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_reports');
    }
};
