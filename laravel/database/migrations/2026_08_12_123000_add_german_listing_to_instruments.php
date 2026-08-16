<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->string('german_listing_symbol', 60)->nullable()->index()->after('provider_symbol');
            $table->string('german_listing_exchange', 80)->nullable()->after('german_listing_symbol');
            $table->string('german_listing_mic', 12)->nullable()->after('german_listing_exchange');
            $table->string('german_listing_currency', 8)->nullable()->after('german_listing_mic');
            $table->timestampTz('german_listing_verified_at')->nullable()->after('german_listing_currency');
        });
    }

    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->dropIndex(['german_listing_symbol']);
            $table->dropColumn(['german_listing_symbol', 'german_listing_exchange', 'german_listing_mic', 'german_listing_currency', 'german_listing_verified_at']);
        });
    }
};
