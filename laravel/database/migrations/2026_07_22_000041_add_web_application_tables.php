<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('legal_accepted')->default(false);
            $table->timestampTz('legal_accepted_at')->nullable();
            $table->string('legal_version', 32)->nullable();
            $table->string('legal_accept_ip', 45)->nullable();
            $table->text('legal_accept_user_agent')->nullable();
            $table->boolean('accepted_terms')->default(false);
            $table->timestampTz('accepted_terms_at')->nullable();
            $table->boolean('accepted_privacy')->default(false);
            $table->timestampTz('accepted_privacy_at')->nullable();
            $table->boolean('accepted_risk_notice')->default(false);
            $table->timestampTz('accepted_risk_notice_at')->nullable();
            $table->jsonb('preferences')->nullable();
            $table->jsonb('meta')->nullable();
        });

        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64)->index();
            $table->string('version', 32);
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestampTz('published_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->unique(['type', 'version']);
        });

        Schema::create('user_legal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->boolean('accepted')->default(true);
            $table->timestampTz('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'legal_document_id']);
        });

        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email');
            $table->string('subject', 180);
            $table->longText('message');
            $table->string('status', 32)->default('new')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('handled_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('rating');
            $table->string('title', 100)->nullable();
            $table->text('comment');
            $table->boolean('is_published')->default(true)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('user_legal_acceptances');
        Schema::dropIfExists('legal_documents');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_accepted',
                'legal_accepted_at',
                'legal_version',
                'legal_accept_ip',
                'legal_accept_user_agent',
                'accepted_terms',
                'accepted_terms_at',
                'accepted_privacy',
                'accepted_privacy_at',
                'accepted_risk_notice',
                'accepted_risk_notice_at',
                'preferences',
                'meta',
            ]);
        });
    }
};
