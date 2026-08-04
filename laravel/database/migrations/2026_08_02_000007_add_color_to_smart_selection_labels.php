<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smart_selection_labels', function (Blueprint $table): void {
            $table->string('color', 7)->default('#14b8a6')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('smart_selection_labels', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }
};
