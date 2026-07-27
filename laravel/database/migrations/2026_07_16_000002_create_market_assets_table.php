<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_assets', function (Blueprint $table) {
            $table->id();

            // Darf beim Import zunächst leer sein
            $table->foreignId('market_snapshot_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('symbol', 32)->index();
            $table->string('name')->nullable();
            $table->string('category', 32)->index();

            $table->decimal('price', 20, 8)->nullable();
            $table->decimal('change_percent', 12, 6)->nullable();
            $table->decimal('volume', 24, 4)->nullable();

            $table->string('signal', 16)->nullable()->index();
            $table->string('trend', 16)->nullable()->index();
            $table->decimal('score', 5, 2)->nullable();

            $table->timestampTz('observed_at')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->unique(['symbol', 'observed_at']);
            $table->index(['symbol', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_assets');
    }
};
