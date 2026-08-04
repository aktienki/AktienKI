<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quality_gate_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('name', 80)->default('Mein Quality Gate');
            $table->boolean('is_active')->default(true);
            $table->jsonb('rules');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quality_gate_profiles');
    }
};
