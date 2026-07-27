<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('algorithm', 64)->index();
            $table->string('task_type', 32)->default('regression')->index();
            $table->string('target_name', 64)->default('target_return_5d');
            $table->string('interval', 10)->default('1d');
            $table->string('feature_version', 32)->default('1.0.0');
            $table->jsonb('default_parameters')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('trained_models', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('model_definition_id')
                ->constrained('model_definitions')
                ->restrictOnDelete();

            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('instrument_id')
                ->nullable()
                ->constrained('instruments')
                ->nullOnDelete();

            $table->string('scope', 24)->default('global')->index();
            $table->string('version', 64);
            $table->string('status', 24)->default('training')->index();

            $table->string('storage_disk', 32)->default('local');
            $table->string('artifact_path');
            $table->string('checksum', 128)->nullable();

            $table->timestampTz('trained_at')->nullable();
            $table->timestampTz('training_period_start')->nullable();
            $table->timestampTz('training_period_end')->nullable();

            $table->unsignedInteger('training_rows')->default(0);
            $table->unsignedInteger('validation_rows')->default(0);
            $table->unsignedInteger('test_rows')->default(0);

            $table->jsonb('parameters')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->jsonb('feature_names')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(
                ['model_definition_id', 'instrument_id', 'version'],
                'trained_models_definition_instrument_version_unique'
            );
        });

        Schema::create('training_runs', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->uuid('public_id')->unique();

            $table->foreignId('model_definition_id')
                ->constrained('model_definitions')
                ->restrictOnDelete();

            $table->foreignId('trained_model_id')
                ->nullable()
                ->constrained('trained_models')
                ->nullOnDelete();

            $table->foreignId('instrument_id')
                ->nullable()
                ->constrained('instruments')
                ->nullOnDelete();

            $table->string('status', 24)->default('running')->index();
            $table->string('feature_version', 32);
            $table->string('target_name', 64);

            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();

            $table->jsonb('parameters')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->text('error_message')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_runs');
        Schema::dropIfExists('trained_models');
        Schema::dropIfExists('model_definitions');
    }
};
