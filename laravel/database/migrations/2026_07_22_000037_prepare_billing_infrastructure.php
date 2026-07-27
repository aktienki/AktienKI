<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariff_plans', function (Blueprint $table): void {
            $table->string('tax_code', 50)->nullable();
            $table->jsonb('external_price_ids')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('billing_metadata')->default(DB::raw("'{}'::jsonb"));
        });
        Schema::table('chat_packages', function (Blueprint $table): void {
            $table->string('tax_code', 50)->nullable();
            $table->jsonb('external_price_ids')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('billing_metadata')->default(DB::raw("'{}'::jsonb"));
        });

        Schema::create('billing_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('external_customer_id', 191)->nullable();
            $table->string('billing_email')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->jsonb('billing_address')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->unique(
                ['provider', 'external_customer_id'],
                'billing_accounts_provider_customer_unique'
            );
        });

        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_account_id')->nullable()
                ->constrained('billing_accounts')->nullOnDelete();
            $table->foreignId('tariff_plan_id')->nullable()
                ->constrained('tariff_plans')->restrictOnDelete();
            $table->foreignId('chat_package_id')->nullable()
                ->constrained('chat_packages')->restrictOnDelete();
            $table->string('product_type', 20);
            $table->string('provider', 40)->nullable();
            $table->string('external_subscription_id', 191)->nullable();
            $table->string('external_subscription_item_id', 191)->nullable();
            $table->string('status', 20)->default('incomplete');
            $table->string('billing_cycle', 10)->default('monthly');
            $table->unsignedInteger('unit_amount_cents');
            $table->char('currency', 3)->default('EUR');
            $table->timestampTz('current_period_start')->nullable();
            $table->timestampTz('current_period_end')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->unique(
                ['provider', 'external_subscription_id'],
                'billing_subscriptions_provider_external_unique'
            );
            $table->index(
                ['user_id', 'product_type', 'status'],
                'billing_subscriptions_user_product_status_idx'
            );
            $table->index('current_period_end');
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_subscription_id')->nullable()
                ->constrained('billing_subscriptions')->nullOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('external_invoice_id', 191)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3)->default('EUR');
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->unsignedBigInteger('amount_paid_cents')->default(0);
            $table->unsignedBigInteger('amount_due_cents')->default(0);
            $table->timestampTz('period_start')->nullable();
            $table->timestampTz('period_end')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('hosted_invoice_url', 2048)->nullable();
            $table->string('invoice_pdf_url', 2048)->nullable();
            $table->jsonb('line_items')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->unique(
                ['provider', 'external_invoice_id'],
                'billing_invoices_provider_external_unique'
            );
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_invoice_id')->nullable()
                ->constrained('billing_invoices')->nullOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('external_payment_id', 191)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('refunded_amount_cents')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('payment_method_type', 40)->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->unique(
                ['provider', 'external_payment_id'],
                'billing_payments_provider_external_unique'
            );
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40);
            $table->string('external_event_id', 191);
            $table->string('event_type', 120);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('payload');
            $table->timestampsTz();
            $table->unique(
                ['provider', 'external_event_id'],
                'billing_webhooks_provider_event_unique'
            );
            $table->index(['status', 'next_retry_at']);
        });

        Schema::create('chat_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_package_id')->nullable()
                ->constrained('chat_packages')->nullOnDelete();
            $table->unsignedInteger('units')->default(1);
            $table->timestampTz('occurred_at')->useCurrent();
            $table->string('usage_type', 40)->default('message');
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->index(['user_id', 'occurred_at']);
        });

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_usage_events');
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('billing_accounts');
        Schema::table('chat_packages', function (Blueprint $table): void {
            $table->dropColumn(['tax_code', 'external_price_ids', 'billing_metadata']);
        });
        Schema::table('tariff_plans', function (Blueprint $table): void {
            $table->dropColumn(['tax_code', 'external_price_ids', 'billing_metadata']);
        });
    }

    private function addChecks(): void
    {
        DB::statement("ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_product_chk CHECK ((product_type='tariff' AND tariff_plan_id IS NOT NULL AND chat_package_id IS NULL) OR (product_type='chat' AND chat_package_id IS NOT NULL AND tariff_plan_id IS NULL))");
        DB::statement("ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_status_chk CHECK (status IN ('incomplete','trialing','active','past_due','paused','cancelled','expired'))");
        DB::statement("ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_cycle_chk CHECK (billing_cycle IN ('monthly','yearly'))");
        DB::statement('ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_period_chk CHECK (current_period_end IS NULL OR current_period_start IS NULL OR current_period_end >= current_period_start)');
        DB::statement("ALTER TABLE billing_invoices ADD CONSTRAINT billing_invoices_status_chk CHECK (status IN ('draft','open','paid','void','uncollectible'))");
        DB::statement('ALTER TABLE billing_invoices ADD CONSTRAINT billing_invoices_amount_chk CHECK (amount_paid_cents <= total_cents AND amount_due_cents <= total_cents)');
        DB::statement("ALTER TABLE billing_payments ADD CONSTRAINT billing_payments_status_chk CHECK (status IN ('pending','processing','succeeded','failed','cancelled','partially_refunded','refunded'))");
        DB::statement('ALTER TABLE billing_payments ADD CONSTRAINT billing_payments_refund_chk CHECK (refunded_amount_cents <= amount_cents)');
        DB::statement("ALTER TABLE billing_webhook_events ADD CONSTRAINT billing_webhook_events_status_chk CHECK (status IN ('pending','processing','processed','failed','ignored'))");
    }
};
