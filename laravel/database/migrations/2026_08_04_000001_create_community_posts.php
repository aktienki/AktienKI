<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('community_alias', 24)->nullable()->unique();
        });

        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 24)->default('general')->index();
            $table->string('title', 100);
            $table->text('body');
            $table->boolean('is_published')->default(true)->index();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['is_published', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['community_alias']);
            $table->dropColumn('community_alias');
        });
    }
};
