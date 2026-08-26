<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etf_funds', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->index();
            $table->string('symbol', 40)->nullable()->index();
            $table->string('isin', 20)->nullable()->unique();
            $table->string('name');
            $table->string('exchange', 80)->nullable();
            $table->string('mic_code', 12)->nullable()->index();
            $table->string('german_listing_symbol', 60)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('domicile', 2)->nullable();
            $table->boolean('is_german_tradeable')->default(false)->index();
            $table->timestampTz('german_tradeability_verified_at')->nullable();
            $table->string('german_tradeability_source')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_format', 20)->default('csv');
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('last_synced_at')->nullable();
            $table->unsignedInteger('current_holding_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestampsTz();
        });

        Schema::create('etf_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etf_fund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->nullable()->constrained('instruments')->nullOnDelete();
            $table->string('holding_isin', 20)->nullable()->index();
            $table->string('holding_symbol', 60)->nullable()->index();
            $table->string('holding_name')->nullable();
            $table->decimal('weight_percent', 12, 6)->nullable();
            $table->date('effective_date')->index();
            $table->timestampTz('source_updated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['etf_fund_id', 'holding_isin', 'effective_date'], 'etf_holdings_fund_isin_date_unique');
            $table->index(['instrument_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etf_holdings');
        Schema::dropIfExists('etf_funds');
    }
};
