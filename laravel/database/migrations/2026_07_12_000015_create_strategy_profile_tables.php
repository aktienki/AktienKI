<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope', 24)->default('global')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->string('target_type', 40)->default('future_return');
            $table->unsignedSmallInteger('target_horizon_days')->default(5);
            $table->string('interval', 10)->default('1d');
            $table->unsignedSmallInteger('history_years')->default(10);
            $table->unsignedInteger('retraining_interval_days')->nullable();
            $table->jsonb('configuration');
            $table->jsonb('allowed_algorithms')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('strategy_profile_instruments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('strategy_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('role', 40)->index();
            $table->string('alias', 80);
            $table->jsonb('parameters')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz();
            $table->unique(['strategy_profile_id', 'instrument_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_profile_instruments');
        Schema::dropIfExists('strategy_profiles');
    }
};
