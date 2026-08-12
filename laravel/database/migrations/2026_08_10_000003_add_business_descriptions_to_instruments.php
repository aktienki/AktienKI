<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->text('business_summary')->nullable();
            $table->text('business_description')->nullable();
            $table->string('business_description_model', 64)->nullable();
            $table->timestampTz('business_description_updated_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table): void {
            $table->dropColumn([
                'business_summary',
                'business_description',
                'business_description_model',
                'business_description_updated_at',
            ]);
        });
    }
};
