<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->decimal('entry_price', 24, 10)->nullable()->after('added_at');
            $table->timestampTz('entry_price_at')->nullable()->after('entry_price');
            $table->string('entry_currency', 3)->nullable()->after('entry_price_at');
        });
    }

    public function down(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->dropColumn(['entry_price', 'entry_price_at', 'entry_currency']);
        });
    }
};
