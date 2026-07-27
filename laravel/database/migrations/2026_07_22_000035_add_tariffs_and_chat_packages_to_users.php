<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('monthly_price_cents')->nullable();
            $table->unsignedInteger('yearly_price_cents')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->jsonb('entitlements')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('limits')->default(DB::raw("'{}'::jsonb"));
            $table->boolean('is_active')->default(true);
            $table->boolean('is_selectable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('chat_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('monthly_price_cents')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->unsignedInteger('message_quota')->nullable();
            $table->jsonb('entitlements')->default(DB::raw("'{}'::jsonb"));
            $table->boolean('is_active')->default(true);
            $table->boolean('is_selectable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::table('tariff_plans')->insertOrIgnore([
            'code' => 'free',
            'name' => 'Free',
            'monthly_price_cents' => 0,
            'yearly_price_cents' => 0,
            'currency' => 'EUR',
            'entitlements' => json_encode(['horizon' => true]),
            'limits' => json_encode([]),
            'is_active' => true,
            'is_selectable' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('chat_packages')->insertOrIgnore([
            'code' => 'no_chat',
            'name' => 'Kein Chat-Paket',
            'monthly_price_cents' => 0,
            'currency' => 'EUR',
            'message_quota' => 0,
            'entitlements' => json_encode([]),
            'is_active' => true,
            'is_selectable' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tariff_plan_id')->nullable()
                ->constrained('tariff_plans')->restrictOnDelete();
            $table->foreignId('chat_package_id')->nullable()
                ->constrained('chat_packages')->restrictOnDelete();
            $table->string('tariff_status', 20)->default('inactive');
            $table->string('billing_cycle', 10)->default('monthly');
            $table->timestampTz('tariff_started_at')->nullable();
            $table->timestampTz('tariff_ends_at')->nullable();
            $table->timestampTz('tariff_cancelled_at')->nullable();
            $table->unsignedInteger('chat_messages_used')->default(0);
            $table->timestampTz('chat_quota_resets_at')->nullable();
            $table->jsonb('subscription_metadata')
                ->default(DB::raw("'{}'::jsonb"));
            $table->index(
                ['tariff_status', 'tariff_ends_at'],
                'users_tariff_status_end_idx'
            );
        });

        $freeId = DB::table('tariff_plans')->where('code', 'free')->value('id');
        $noChatId = DB::table('chat_packages')->where('code', 'no_chat')->value('id');
        DB::table('users')->whereNull('tariff_plan_id')->update([
            'tariff_plan_id' => $freeId,
        ]);
        DB::table('users')->whereNull('chat_package_id')->update([
            'chat_package_id' => $noChatId,
        ]);

        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_tariff_status_chk "
            . "CHECK (tariff_status IN "
            . "('inactive','trialing','active','past_due','cancelled','expired'))"
        );
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_billing_cycle_chk "
            . "CHECK (billing_cycle IN ('monthly','yearly'))"
        );
        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_tariff_period_chk '
            . 'CHECK (tariff_ends_at IS NULL OR tariff_started_at IS NULL '
            . 'OR tariff_ends_at >= tariff_started_at)'
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_tariff_status_end_idx');
            $table->dropConstrainedForeignId('tariff_plan_id');
            $table->dropConstrainedForeignId('chat_package_id');
            $table->dropColumn([
                'tariff_status', 'billing_cycle', 'tariff_started_at',
                'tariff_ends_at', 'tariff_cancelled_at', 'chat_messages_used',
                'chat_quota_resets_at', 'subscription_metadata',
            ]);
        });
        Schema::dropIfExists('chat_packages');
        Schema::dropIfExists('tariff_plans');
    }
};
