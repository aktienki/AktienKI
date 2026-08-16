<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->boolean('is_german_tradeable')->nullable()->index()->after('german_listing_verified_at');
            $table->timestampTz('german_listing_checked_at')->nullable()->after('is_german_tradeable');
        });
    }

    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->dropIndex(['is_german_tradeable']);
            $table->dropColumn(['is_german_tradeable', 'german_listing_checked_at']);
        });
    }
};
