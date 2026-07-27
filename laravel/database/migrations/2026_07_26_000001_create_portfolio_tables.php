<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 24)->default('standard');
            $table->string('currency', 3)->default('EUR');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'active']);
        });

        Schema::create('portfolio_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->decimal('quantity', 20, 8);
            $table->decimal('average_buy_price', 20, 6);
            $table->decimal('current_price', 20, 6)->nullable();
            $table->date('opened_at_date')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['portfolio_id', 'instrument_id']);
        });

        Schema::create('portfolio_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('type', 16);
            $table->date('transaction_date');
            $table->decimal('quantity', 20, 8);
            $table->decimal('price', 20, 6);
            $table->decimal('fees', 20, 6)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->index(['portfolio_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_transactions');
        Schema::dropIfExists('portfolio_positions');
        Schema::dropIfExists('portfolios');
    }
};
