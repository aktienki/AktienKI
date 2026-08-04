<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smart_selection_labels', function (Blueprint $table): void {
            $table->string('icon', 32)->default('sparkles')->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('smart_selection_labels', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }
};
