<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_model_preferences', function (Blueprint $table): void {
            $table->boolean('fallback_to_champion')->default(true);
            $table->boolean('requires_premium')->default(true);
            $table->timestampTz('selected_at')->nullable();
        });

        DB::statement(
            'ALTER TABLE user_model_preferences '
            .'DROP CONSTRAINT user_model_preferences_mode_check'
        );
        DB::statement(<<<'SQL'
            ALTER TABLE user_model_preferences
            ADD CONSTRAINT user_model_preferences_mode_check CHECK (
                (selection_mode = 'family'
                 AND preferred_model_name IS NOT NULL
                 AND trained_model_id IS NULL)
                OR
                (selection_mode = 'version'
                 AND preferred_model_name IS NULL
                 AND trained_model_id IS NOT NULL)
                OR
                (selection_mode IN ('champion', 'challenger')
                 AND preferred_model_name IS NULL
                 AND trained_model_id IS NULL)
            )
            SQL);

        DB::statement(<<<'SQL'
            UPDATE user_model_preferences
            SET selected_at = COALESCE(updated_at, created_at, NOW())
            WHERE selected_at IS NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE user_model_preferences '
            .'DROP CONSTRAINT user_model_preferences_mode_check'
        );
        DB::statement(<<<'SQL'
            ALTER TABLE user_model_preferences
            ADD CONSTRAINT user_model_preferences_mode_check CHECK (
                (selection_mode = 'family'
                 AND preferred_model_name IS NOT NULL
                 AND trained_model_id IS NULL)
                OR
                (selection_mode = 'version'
                 AND preferred_model_name IS NULL
                 AND trained_model_id IS NOT NULL)
                OR
                (selection_mode = 'champion'
                 AND preferred_model_name IS NULL
                 AND trained_model_id IS NULL)
            )
            SQL);
        Schema::table('user_model_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'fallback_to_champion', 'requires_premium', 'selected_at',
            ]);
        });
    }
};
