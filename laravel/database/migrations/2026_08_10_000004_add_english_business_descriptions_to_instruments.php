<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->text('business_summary_en')->nullable();
            $table->text('business_description_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->dropColumn(['business_summary_en', 'business_description_en']);
        });
    }
};
