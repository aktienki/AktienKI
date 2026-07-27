<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->uuid('training_batch_id')->nullable();
            $table->string('model_role', 30)->default('candidate');
            $table->timestampTz('batch_started_at')->nullable();
            $table->timestampTz('batch_finished_at')->nullable();

            $table->index(
                ['instrument_id', 'training_batch_id', 'model_role'],
                'trained_models_batch_comparison_idx'
            );
        });

        DB::statement(<<<'SQL'
            UPDATE trained_models
            SET training_batch_id = NULLIF(metadata->>'training_batch_id', '')::uuid,
                model_role = COALESCE(metadata->>'model_role', model_role),
                batch_started_at = NULLIF(metadata->>'batch_started_at', '')::timestamptz,
                batch_finished_at = NULLIF(metadata->>'batch_finished_at', '')::timestamptz
            WHERE jsonb_exists(metadata, 'training_batch_id')
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_trained_model_batch_details()
            RETURNS trigger AS $$
            BEGIN
                NEW.training_batch_id := COALESCE(
                    NEW.training_batch_id,
                    NULLIF(NEW.metadata->>'training_batch_id', '')::uuid
                );
                NEW.model_role := COALESCE(
                    NEW.metadata->>'model_role', NEW.model_role
                );
                NEW.batch_started_at := COALESCE(
                    NEW.batch_started_at,
                    NULLIF(NEW.metadata->>'batch_started_at', '')::timestamptz
                );
                NEW.batch_finished_at := COALESCE(
                    NEW.batch_finished_at,
                    NULLIF(NEW.metadata->>'batch_finished_at', '')::timestamptz
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trained_models_sync_batch_details
            BEFORE INSERT OR UPDATE OF metadata ON trained_models
            FOR EACH ROW EXECUTE FUNCTION sync_trained_model_batch_details()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS trained_models_sync_batch_details ON trained_models'
        );
        DB::statement('DROP FUNCTION IF EXISTS sync_trained_model_batch_details()');
        Schema::table('trained_models', function (Blueprint $table): void {
            $table->dropIndex('trained_models_batch_comparison_idx');
            $table->dropColumn([
                'training_batch_id', 'model_role',
                'batch_started_at', 'batch_finished_at',
            ]);
        });
    }
};
