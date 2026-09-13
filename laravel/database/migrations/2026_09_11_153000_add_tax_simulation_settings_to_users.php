<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('tax_allowance_eur', 12, 2)->default(1000.00);
            $table->decimal('tax_rate_percent', 5, 2)->default(25.00);
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_tax_allowance_eur_chk CHECK (tax_allowance_eur >= 0)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_tax_rate_percent_chk CHECK (tax_rate_percent >= 0 AND tax_rate_percent <= 100)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_tax_allowance_eur_chk');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_tax_rate_percent_chk');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['tax_allowance_eur', 'tax_rate_percent']);
        });
    }
};
