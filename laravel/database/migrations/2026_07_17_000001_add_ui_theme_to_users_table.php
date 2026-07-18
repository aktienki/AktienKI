<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('ui_theme', 30)
                ->default('purple')
                ->after('subscription_plan');

            $table->jsonb('ui_preferences')
                ->nullable()
                ->after('ui_theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'ui_theme',
                'ui_preferences',
            ]);
        });
    }
};
