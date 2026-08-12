<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->decimal('predicted_price_15d', 20, 8)->nullable();
            $table->decimal('price_difference_15d', 20, 8)->nullable();
            $table->decimal('market_return_15d', 12, 8)->nullable();
            $table->decimal('long_return_15d', 12, 8)->nullable();
            $table->decimal('short_return_15d', 12, 8)->nullable();
            $table->decimal('strategy_return_15d', 12, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropColumn(['predicted_price_15d', 'price_difference_15d', 'market_return_15d', 'long_return_15d', 'short_return_15d', 'strategy_return_15d']);
        });
    }
};
