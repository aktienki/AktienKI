<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aki_chat_usages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20);
            $table->string('model', 80);
            $table->string('status', 20)->default('reserved');
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cost_micros_eur')->default(0);
            $table->unsignedBigInteger('reserved_micros_eur')->default(0);
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('occurred_at')->useCurrent();
            $table->timestampsTz();
            $table->index(['user_id', 'occurred_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aki_chat_usages');
    }
};
