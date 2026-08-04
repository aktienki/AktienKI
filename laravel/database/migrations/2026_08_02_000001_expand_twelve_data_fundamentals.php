<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instrument_fundamentals', function (Blueprint $table): void {
            $table->date('fiscal_date')->nullable()->after('snapshot_date')->index();
            $table->timestampTz('reported_at')->nullable()->after('fiscal_date')->index();
            $table->timestampTz('retrieved_at')->nullable()->after('reported_at')->index();
            $table->decimal('market_cap', 24, 2)->nullable();
            $table->decimal('enterprise_value', 24, 2)->nullable();
            $table->decimal('trailing_pe', 14, 4)->nullable();
            $table->decimal('forward_pe', 14, 4)->nullable();
            $table->decimal('peg_ratio', 14, 4)->nullable();
            $table->decimal('price_to_book', 14, 4)->nullable();
            $table->decimal('price_to_sales', 14, 4)->nullable();
            $table->decimal('dividend_rate', 18, 6)->nullable();
            $table->decimal('dividend_yield', 14, 6)->nullable();
            $table->decimal('payout_ratio', 14, 6)->nullable();
            $table->decimal('profit_margin', 14, 6)->nullable();
            $table->decimal('operating_margin', 14, 6)->nullable();
            $table->decimal('return_on_assets', 14, 6)->nullable();
            $table->decimal('return_on_equity', 14, 6)->nullable();
            $table->decimal('revenue', 24, 2)->nullable();
            $table->decimal('revenue_growth', 14, 6)->nullable();
            $table->decimal('gross_profit', 24, 2)->nullable();
            $table->decimal('ebitda', 24, 2)->nullable();
            $table->decimal('net_income', 24, 2)->nullable();
            $table->decimal('total_cash', 24, 2)->nullable();
            $table->decimal('total_debt', 24, 2)->nullable();
            $table->decimal('debt_to_equity', 14, 6)->nullable();
            $table->decimal('current_ratio', 14, 6)->nullable();
            $table->decimal('quick_ratio', 14, 6)->nullable();
            $table->decimal('operating_cash_flow', 24, 2)->nullable();
            $table->decimal('free_cash_flow', 24, 2)->nullable();
            $table->decimal('shares_outstanding', 24, 2)->nullable();
            $table->decimal('float_shares', 24, 2)->nullable();
            $table->jsonb('raw_data')->nullable();
        });

        Schema::create('instrument_financial_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('statement_type', 24);
            $table->date('fiscal_date');
            $table->string('period', 16)->default('unknown');
            $table->string('currency', 8)->nullable();
            $table->timestampTz('reported_at')->nullable()->index();
            $table->timestampTz('retrieved_at')->index();
            $table->jsonb('data');
            $table->string('source', 32)->default('twelve_data');
            $table->timestampsTz();
            $table->unique(['instrument_id', 'statement_type', 'fiscal_date', 'period'], 'instrument_statement_period_unique');
            $table->index(['instrument_id', 'fiscal_date']);
        });

        Schema::create('instrument_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('earnings_date');
            $table->string('period', 16)->default('unknown');
            $table->decimal('eps_estimate', 18, 6)->nullable();
            $table->decimal('eps_actual', 18, 6)->nullable();
            $table->decimal('surprise_percent', 14, 6)->nullable();
            $table->timestampTz('retrieved_at')->index();
            $table->jsonb('data');
            $table->string('source', 32)->default('twelve_data');
            $table->timestampsTz();
            $table->unique(['instrument_id', 'earnings_date', 'period']);
        });

        Schema::create('instrument_dividends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('ex_date');
            $table->date('record_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 18, 6)->nullable();
            $table->string('currency', 8)->nullable();
            $table->timestampTz('retrieved_at')->index();
            $table->jsonb('data');
            $table->string('source', 32)->default('twelve_data');
            $table->timestampsTz();
            $table->unique(['instrument_id', 'ex_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_dividends');
        Schema::dropIfExists('instrument_earnings');
        Schema::dropIfExists('instrument_financial_statements');

        Schema::table('instrument_fundamentals', function (Blueprint $table): void {
            $table->dropColumn([
                'fiscal_date', 'reported_at', 'retrieved_at', 'market_cap', 'enterprise_value',
                'trailing_pe', 'forward_pe', 'peg_ratio', 'price_to_book', 'price_to_sales',
                'dividend_rate', 'dividend_yield', 'payout_ratio', 'profit_margin', 'operating_margin',
                'return_on_assets', 'return_on_equity', 'revenue', 'revenue_growth', 'gross_profit',
                'ebitda', 'net_income', 'total_cash', 'total_debt', 'debt_to_equity', 'current_ratio',
                'quick_ratio', 'operating_cash_flow', 'free_cash_flow', 'shares_outstanding',
                'float_shares', 'raw_data',
            ]);
        });
    }
};
