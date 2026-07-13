<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_definitions', function (Blueprint $table): void {
            $table->foreignId('strategy_profile_id')
                ->nullable()
                ->after('id')
                ->constrained('strategy_profiles')
                ->nullOnDelete();

            $table->unsignedInteger('strategy_profile_version')
                ->nullable()
                ->after('strategy_profile_id');
        });

        Schema::table('training_runs', function (Blueprint $table): void {
            $table->foreignId('strategy_profile_id')
                ->nullable()
                ->after('public_id')
                ->constrained('strategy_profiles')
                ->nullOnDelete();

            $table->unsignedInteger('strategy_profile_version')
                ->nullable()
                ->after('strategy_profile_id');

            $table->jsonb('resolved_configuration')
                ->nullable()
                ->after('parameters');
        });
    }

    public function down(): void
    {
        Schema::table('training_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('strategy_profile_id');

            $table->dropColumn([
                'strategy_profile_version',
                'resolved_configuration',
            ]);
        });

        Schema::table('model_definitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('strategy_profile_id');
            $table->dropColumn('strategy_profile_version');
        });
    }
};