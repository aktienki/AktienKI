<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_beta_tester')->default(false)->index();
        });

        // Preserve the existing tester cohort while moving the flag to a first-class field.
        \DB::table('users')
            ->where('account_status', 'tester')
            ->update(['is_beta_tester' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_beta_tester');
        });
    }
};
