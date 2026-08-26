<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_securities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('underlying_instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('type', 30)->index(); // discount_certificate, bonus_certificate, bond
            $table->string('isin', 20)->unique();
            $table->string('wkn', 12)->nullable()->index();
            $table->string('name');
            $table->string('issuer')->nullable()->index();
            $table->string('currency', 3)->default('EUR');
            $table->string('exchange', 80);
            $table->string('mic_code', 12)->index();
            $table->string('german_listing_symbol', 60)->nullable();
            $table->date('maturity_date')->nullable()->index();
            $table->decimal('price', 20, 6)->nullable();
            $table->decimal('cap', 20, 6)->nullable();
            $table->decimal('barrier', 20, 6)->nullable();
            $table->decimal('bonus_level', 20, 6)->nullable();
            $table->decimal('coupon_percent', 10, 4)->nullable();
            $table->decimal('discount_percent', 10, 4)->nullable();
            $table->timestampTz('quote_at')->nullable();
            $table->timestampTz('german_tradeability_verified_at')->index();
            $table->string('source_provider');
            $table->text('source_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('meta')->nullable();
            $table->timestampsTz();
            $table->index(['underlying_instrument_id', 'type', 'is_active'], 'linked_securities_underlying_type_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linked_securities');
    }
};
