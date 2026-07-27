<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendSubscriptions();
        $this->extendInvoicesAndPayments();
        $this->createLedgerTables();
        $this->addIntegrityRules();
    }

    private function extendSubscriptions(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->string('product_code', 50)->nullable();
            $table->string('product_name', 100)->nullable();
            $table->string('external_price_id', 191)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('collection_method', 20)->default('automatic');
            $table->timestampTz('grace_period_ends_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('resumes_at')->nullable();
        });
        DB::statement(
            "CREATE UNIQUE INDEX billing_subscriptions_one_live_product_idx "
            . "ON billing_subscriptions (user_id, product_type) "
            . "WHERE status IN ('incomplete','trialing','active','past_due','paused')"
        );
    }

    private function extendInvoicesAndPayments(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_company')->nullable();
            $table->string('customer_tax_id', 100)->nullable();
            $table->string('tax_country_code', 2)->nullable();
            $table->boolean('reverse_charge')->default(false);
            $table->jsonb('billing_address_snapshot')
                ->default(DB::raw("'{}'::jsonb"));
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestampTz('finalized_at')->nullable();
            $table->text('footer')->nullable();
        });
        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->string('external_payment_method_id', 191)->nullable();
            $table->unsignedBigInteger('fee_cents')->default(0);
            $table->bigInteger('net_cents')->nullable();
            $table->timestampTz('settled_at')->nullable();
        });
        Schema::table('billing_webhook_events', function (Blueprint $table): void {
            $table->char('payload_sha256', 64)->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->timestampTz('payload_expires_at')->nullable();
        });
    }

    private function createLedgerTables(): void
    {
        Schema::create('billing_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('external_payment_method_id', 191);
            $table->string('type', 40);
            $table->string('display_brand', 40)->nullable();
            $table->string('display_last_four', 4)->nullable();
            $table->unsignedSmallInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->unique(
                ['provider', 'external_payment_method_id'],
                'billing_payment_methods_provider_external_unique'
            );
        });
        DB::statement(
            'CREATE UNIQUE INDEX billing_payment_methods_one_default_idx '
            . 'ON billing_payment_methods (billing_account_id, provider) '
            . 'WHERE is_default=TRUE'
        );

        Schema::create('billing_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_subscription_id')->nullable()
                ->constrained('billing_subscriptions')->nullOnDelete();
            $table->string('external_item_id', 191)->nullable();
            $table->string('product_type', 20)->nullable();
            $table->string('product_code', 50)->nullable();
            $table->text('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_amount_cents');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->unsignedInteger('tax_rate_basis_points')->nullable();
            $table->timestampTz('service_period_start')->nullable();
            $table->timestampTz('service_period_end')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->index(['billing_invoice_id', 'product_type']);
        });

        Schema::create('billing_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('external_credit_note_id', 191)->nullable();
            $table->string('credit_note_number', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('reason', 40)->nullable();
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->timestampTz('issued_at')->nullable();
            $table->string('document_url', 2048)->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->unique(
                ['provider', 'external_credit_note_id'],
                'billing_credit_notes_provider_external_unique'
            );
        });

        Schema::create('billing_credit_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_invoice_item_id')->nullable()
                ->constrained('billing_invoice_items')->nullOnDelete();
            $table->text('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_cents');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
        });

        Schema::create('billing_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('external_refund_id', 191)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('reason', 40)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('EUR');
            $table->timestampTz('refunded_at')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->unique(
                ['provider', 'external_refund_id'],
                'billing_refunds_provider_external_unique'
            );
        });

        Schema::create('billing_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('external_dispute_id', 191);
            $table->string('status', 30);
            $table->string('reason', 60)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('EUR');
            $table->timestampTz('evidence_due_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->unique(['provider', 'external_dispute_id']);
        });

        Schema::create('billing_subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('billing_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->jsonb('before')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('after')->default(DB::raw("'{}'::jsonb"));
            $table->timestampTz('occurred_at')->useCurrent();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('billing_entitlement_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entitlement', 100);
            $table->string('source_type', 30);
            $table->string('source_reference', 191)->nullable();
            $table->timestampTz('starts_at')->useCurrent();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
            $table->index(['user_id', 'entitlement', 'ends_at']);
        });

        Schema::create('billing_outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('aggregate_type', 50);
            $table->string('aggregate_id', 191);
            $table->string('event_type', 100);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('available_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('payload');
            $table->timestampsTz();
            $table->index(['status', 'available_at']);
        });
    }

    private function addIntegrityRules(): void
    {
        DB::statement("ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_collection_chk CHECK (collection_method IN ('automatic','invoice','manual'))");
        DB::statement('ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_quantity_chk CHECK (quantity > 0)');
        DB::statement("ALTER TABLE billing_credit_notes ADD CONSTRAINT billing_credit_notes_status_chk CHECK (status IN ('draft','issued','void'))");
        DB::statement("ALTER TABLE billing_refunds ADD CONSTRAINT billing_refunds_status_chk CHECK (status IN ('pending','succeeded','failed','cancelled'))");
        DB::statement("ALTER TABLE billing_outbox_events ADD CONSTRAINT billing_outbox_status_chk CHECK (status IN ('pending','processing','processed','failed'))");
        DB::statement('ALTER TABLE billing_payment_methods ADD CONSTRAINT billing_payment_methods_expiry_chk CHECK ((expiry_month IS NULL OR expiry_month BETWEEN 1 AND 12) AND (expiry_year IS NULL OR expiry_year >= 2000))');
        DB::statement("ALTER TABLE billing_webhook_events ADD CONSTRAINT billing_webhook_payload_hash_chk CHECK (payload_sha256 IS NULL OR payload_sha256 ~ '^[0-9a-f]{64}$')");
        foreach (['billing_subscriptions', 'billing_invoices', 'billing_payments', 'billing_credit_notes', 'billing_refunds', 'billing_disputes'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_currency_chk CHECK (currency ~ '^[A-Z]{3}$')");
        }
        DB::statement('ALTER TABLE billing_invoice_items ADD CONSTRAINT billing_invoice_items_period_chk CHECK (service_period_end IS NULL OR service_period_start IS NULL OR service_period_end >= service_period_start)');
        DB::statement('ALTER TABLE billing_entitlement_grants ADD CONSTRAINT billing_entitlement_period_chk CHECK (ends_at IS NULL OR ends_at >= starts_at)');
        DB::statement('ALTER TABLE tariff_plans ADD CONSTRAINT tariff_plans_amounts_chk CHECK ((monthly_price_cents IS NULL OR monthly_price_cents >= 0) AND (yearly_price_cents IS NULL OR yearly_price_cents >= 0))');
        DB::statement('ALTER TABLE chat_packages ADD CONSTRAINT chat_packages_amounts_chk CHECK ((monthly_price_cents IS NULL OR monthly_price_cents >= 0) AND (message_quota IS NULL OR message_quota >= 0))');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_chat_usage_chk CHECK (chat_messages_used >= 0)');
        DB::statement('ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_amount_chk CHECK (unit_amount_cents >= 0)');
        DB::statement('ALTER TABLE billing_invoices ADD CONSTRAINT billing_invoices_nonnegative_chk CHECK (subtotal_cents >= 0 AND discount_cents >= 0 AND tax_cents >= 0 AND total_cents >= 0 AND amount_paid_cents >= 0 AND amount_due_cents >= 0)');
        DB::statement('ALTER TABLE billing_payments ADD CONSTRAINT billing_payments_nonnegative_chk CHECK (amount_cents >= 0 AND refunded_amount_cents >= 0 AND fee_cents >= 0)');
        DB::statement('ALTER TABLE billing_invoice_items ADD CONSTRAINT billing_invoice_items_amount_chk CHECK (quantity > 0 AND discount_cents >= 0 AND tax_cents >= 0)');
        DB::statement('ALTER TABLE billing_credit_notes ADD CONSTRAINT billing_credit_notes_amount_chk CHECK (subtotal_cents >= 0 AND tax_cents >= 0 AND total_cents >= 0)');
        DB::statement('ALTER TABLE billing_credit_note_items ADD CONSTRAINT billing_credit_note_items_amount_chk CHECK (quantity > 0 AND unit_amount_cents >= 0 AND subtotal_cents >= 0 AND tax_cents >= 0 AND total_cents >= 0)');
        DB::statement('ALTER TABLE billing_refunds ADD CONSTRAINT billing_refunds_amount_chk CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE billing_disputes ADD CONSTRAINT billing_disputes_amount_chk CHECK (amount_cents > 0)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS billing_subscriptions_one_live_product_idx');
        DB::statement('DROP INDEX IF EXISTS billing_payment_methods_one_default_idx');
        Schema::dropIfExists('billing_outbox_events');
        Schema::dropIfExists('billing_entitlement_grants');
        Schema::dropIfExists('billing_subscription_events');
        Schema::dropIfExists('billing_disputes');
        Schema::dropIfExists('billing_refunds');
        Schema::dropIfExists('billing_credit_note_items');
        Schema::dropIfExists('billing_credit_notes');
        Schema::dropIfExists('billing_invoice_items');
        Schema::dropIfExists('billing_payment_methods');
        Schema::table('billing_webhook_events', function (Blueprint $table): void {
            $table->dropColumn(['payload_sha256', 'signature_verified', 'payload_expires_at']);
        });
        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->dropColumn(['external_payment_method_id', 'fee_cents', 'net_cents', 'settled_at']);
        });
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropColumn(['customer_name', 'customer_email', 'customer_company', 'customer_tax_id', 'tax_country_code', 'reverse_charge', 'billing_address_snapshot', 'schema_version', 'finalized_at', 'footer']);
        });
        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['product_code', 'product_name', 'external_price_id', 'quantity', 'collection_method', 'grace_period_ends_at', 'paused_at', 'resumes_at']);
        });
    }
};
