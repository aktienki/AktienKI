<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_trade_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('prediction_id')->nullable()->constrained('predictions')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->boolean('notify_on_buy')->default(false);
            $table->timestampTz('detected_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('viewed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('snapshot')->default('{}');
            $table->timestampsTz();

            $table->unique(['user_id', 'instrument_id']);
            $table->index(['user_id', 'status', 'expires_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_trade_opportunities');
    }
};
