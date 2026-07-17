<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('code',20)->unique();
            $table->string('mic',20)->nullable();
            $table->string('name');
            $table->string('country',2)->index();
            $table->string('currency',3);
            $table->string('timezone');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->index(['country','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchanges');
    }
};
