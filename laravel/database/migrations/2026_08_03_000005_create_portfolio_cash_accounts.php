<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('balance', 20, 6)->default(0);
            $table->decimal('reserved_balance', 20, 6)->default(0);
            $table->timestampsTz();
            $table->unique(['portfolio_id', 'currency']);
        });
        DB::statement('ALTER TABLE portfolio_cash_accounts ADD CONSTRAINT portfolio_cash_balance_nonnegative_chk CHECK (balance >= 0)');
        DB::statement('ALTER TABLE portfolio_cash_accounts ADD CONSTRAINT portfolio_cash_reserved_nonnegative_chk CHECK (reserved_balance >= 0 AND reserved_balance <= balance)');

        Schema::create('portfolio_cash_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portfolio_cash_account_id')->constrained('portfolio_cash_accounts')->cascadeOnDelete();
            $table->foreignId('portfolio_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->index();
            $table->decimal('amount', 20, 6);
            $table->decimal('balance_after', 20, 6);
            $table->string('currency', 3);
            $table->timestampTz('occurred_at')->index();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->index(['portfolio_cash_account_id', 'occurred_at']);
        });

        DB::table('portfolios')->orderBy('id')->each(function (object $portfolio): void {
            $meta = is_string($portfolio->meta) ? (json_decode($portfolio->meta, true) ?: []) : (array) $portfolio->meta;
            $initial = max(0, (float) data_get($meta, 'automation.initial_capital', 10000));
            $createdAt = $portfolio->created_at ?? now();
            $accountId = DB::table('portfolio_cash_accounts')->insertGetId([
                'portfolio_id' => $portfolio->id, 'currency' => $portfolio->currency,
                'balance' => $initial, 'reserved_balance' => 0,
                'created_at' => $createdAt, 'updated_at' => now(),
            ]);
            $balance = $initial;
            DB::table('portfolio_cash_ledger')->insert([
                'portfolio_cash_account_id' => $accountId, 'type' => 'initial_deposit',
                'amount' => $initial, 'balance_after' => $balance, 'currency' => $portfolio->currency,
                'occurred_at' => $createdAt, 'meta' => json_encode(['source' => 'migration_backfill']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('portfolio_transactions')->where('portfolio_id', $portfolio->id)
                ->orderBy('transaction_date')->orderBy('id')->each(function (object $transaction) use ($accountId, $portfolio, &$balance): void {
                    $gross = (float) $transaction->quantity * (float) $transaction->price;
                    $signed = strtolower((string) $transaction->type) === 'sell' ? $gross : -$gross;
                    $balance += $signed;
                    DB::table('portfolio_cash_ledger')->insert([
                        'portfolio_cash_account_id' => $accountId, 'portfolio_transaction_id' => $transaction->id,
                        'type' => strtolower((string) $transaction->type) === 'sell' ? 'sale_credit' : 'purchase_debit',
                        'amount' => $signed, 'balance_after' => max(0, $balance), 'currency' => $portfolio->currency,
                        'occurred_at' => $transaction->created_at ?? $transaction->transaction_date,
                        'meta' => json_encode(['source' => 'migration_backfill', 'original_currency' => $transaction->currency]),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $fees = max(0, (float) $transaction->fees);
                    if ($fees > 0) {
                        $balance -= $fees;
                        DB::table('portfolio_cash_ledger')->insert([
                            'portfolio_cash_account_id' => $accountId, 'portfolio_transaction_id' => $transaction->id,
                            'type' => 'fee', 'amount' => -$fees, 'balance_after' => max(0, $balance),
                            'currency' => $portfolio->currency, 'occurred_at' => $transaction->created_at ?? $transaction->transaction_date,
                            'meta' => json_encode(['source' => 'migration_backfill']),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                });
            DB::table('portfolio_cash_accounts')->where('id', $accountId)->update([
                'balance' => max(0, $balance), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_cash_ledger');
        Schema::dropIfExists('portfolio_cash_accounts');
    }
};
