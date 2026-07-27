<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduler_jobs', function (Blueprint $table): void {
            $table->unsignedInteger('consecutive_failures')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('scheduler_jobs', function (Blueprint $table): void {
            $table->dropColumn('consecutive_failures');
        });
    }
};
