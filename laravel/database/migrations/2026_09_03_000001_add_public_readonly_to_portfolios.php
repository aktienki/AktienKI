<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table): void {
            $table->boolean('is_public_readonly')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', fn (Blueprint $table) => $table->dropColumn('is_public_readonly'));
    }
};
