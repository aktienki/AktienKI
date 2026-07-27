<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('watchlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('watchlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('added_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->decimal('alert_price_above', 24, 10)->nullable();
            $table->decimal('alert_price_below', 24, 10)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['watchlist_id', 'instrument_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_items');
        Schema::dropIfExists('watchlists');
    }
};
