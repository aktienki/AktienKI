<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->nullable()->constrained('exchanges')->nullOnDelete();

            $table->string('type',20)->index();
            $table->string('symbol',30);
            $table->string('provider_symbol',60)->nullable()->index();
            $table->string('isin',20)->nullable()->index();

            $table->string('name');
            $table->string('short_name')->nullable();

            $table->string('country',2)->nullable()->index();
            $table->string('currency',3)->nullable()->index();
            $table->string('sector')->nullable()->index();
            $table->string('industry')->nullable()->index();

            $table->decimal('market_cap',24,2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_tradeable')->default(true);

            $table->json('meta')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['exchange_id','symbol','type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
