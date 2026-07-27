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
            $table->string('risk_profile', 24)->default('balanced')->index();
        });
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_risk_profile_chk "
            . "CHECK (risk_profile IN "
            . "('standard_champion','conservative','balanced',"
            . "'opportunity','aggressive'))"
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['risk_profile']);
            $table->dropColumn('risk_profile');
        });
    }
};
