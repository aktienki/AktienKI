<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('model_definitions')
            && ! Schema::hasColumn(
                'model_definitions',
                'strategy_profile_id'
            )
        ) {
            Schema::table(
                'model_definitions',
                function (Blueprint $table): void {
                    $table->foreignId('strategy_profile_id')
                        ->nullable()
                        ->constrained('strategy_profiles')
                        ->nullOnDelete();
                }
            );
        }

        if (
            Schema::hasTable('model_definitions')
            && ! Schema::hasColumn(
                'model_definitions',
                'strategy_profile_version'
            )
        ) {
            Schema::table(
                'model_definitions',
                function (Blueprint $table): void {
                    $table->unsignedInteger(
                        'strategy_profile_version'
                    )->nullable();
                }
            );
        }

        if (
            Schema::hasTable('training_runs')
            && ! Schema::hasColumn(
                'training_runs',
                'strategy_profile_id'
            )
        ) {
            Schema::table(
                'training_runs',
                function (Blueprint $table): void {
                    $table->foreignId('strategy_profile_id')
                        ->nullable()
                        ->constrained('strategy_profiles')
                        ->nullOnDelete();
                }
            );
        }

        if (
            Schema::hasTable('training_runs')
            && ! Schema::hasColumn(
                'training_runs',
                'strategy_profile_version'
            )
        ) {
            Schema::table(
                'training_runs',
                function (Blueprint $table): void {
                    $table->unsignedInteger(
                        'strategy_profile_version'
                    )->nullable();
                }
            );
        }

        if (
            Schema::hasTable('training_runs')
            && ! Schema::hasColumn(
                'training_runs',
                'resolved_configuration'
            )
        ) {
            Schema::table(
                'training_runs',
                function (Blueprint $table): void {
                    $table->json(
                        'resolved_configuration'
                    )->nullable();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Diese Migration ergänzt nur fehlende Kompatibilitätsfelder.
         *
         * Die Spalten können im aktuellen Schema bereits von früheren
         * Basismigrationen angelegt worden sein. Deshalb werden sie hier
         * beim Rollback bewusst nicht entfernt.
         */
    }
};
