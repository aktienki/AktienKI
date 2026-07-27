<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE model_portfolios (
                id BIGSERIAL PRIMARY KEY,
                strategy_key VARCHAR(40) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                initial_capital NUMERIC(18,4) NOT NULL CHECK (initial_capital > 0),
                cash_balance NUMERIC(18,4) NOT NULL CHECK (cash_balance >= 0),
                transaction_cost_rate NUMERIC(10,8) NOT NULL DEFAULT 0.002,
                minimum_order_cost NUMERIC(18,4) NOT NULL DEFAULT 10,
                target_position_count INTEGER NOT NULL CHECK (target_position_count > 0),
                rebalance_frequency VARCHAR(20) NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                configuration JSONB NOT NULL DEFAULT '{}'::jsonb,
                last_rebalanced_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );

            CREATE TABLE model_portfolio_runs (
                id BIGSERIAL PRIMARY KEY,
                portfolio_id BIGINT NOT NULL REFERENCES model_portfolios(id),
                status VARCHAR(20) NOT NULL,
                started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                finished_at TIMESTAMPTZ,
                portfolio_value_before NUMERIC(18,4),
                portfolio_value_after NUMERIC(18,4),
                total_cost NUMERIC(18,4) NOT NULL DEFAULT 0,
                target_snapshot JSONB NOT NULL DEFAULT '[]'::jsonb,
                details JSONB NOT NULL DEFAULT '{}'::jsonb,
                error_message TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );

            CREATE TABLE model_portfolio_positions (
                id BIGSERIAL PRIMARY KEY,
                portfolio_id BIGINT NOT NULL REFERENCES model_portfolios(id),
                instrument_id BIGINT NOT NULL REFERENCES instruments(id),
                quantity NUMERIC(24,8) NOT NULL CHECK (quantity >= 0),
                average_cost NUMERIC(18,8) NOT NULL,
                target_weight NUMERIC(12,8) NOT NULL DEFAULT 0,
                strategy_score NUMERIC(18,8),
                opened_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                UNIQUE (portfolio_id, instrument_id)
            );

            CREATE TABLE model_portfolio_transactions (
                id BIGSERIAL PRIMARY KEY,
                portfolio_id BIGINT NOT NULL REFERENCES model_portfolios(id),
                run_id BIGINT NOT NULL REFERENCES model_portfolio_runs(id),
                instrument_id BIGINT NOT NULL REFERENCES instruments(id),
                side VARCHAR(4) NOT NULL CHECK (side IN ('BUY','SELL')),
                quantity NUMERIC(24,8) NOT NULL CHECK (quantity > 0),
                price NUMERIC(18,8) NOT NULL CHECK (price > 0),
                gross_amount NUMERIC(18,4) NOT NULL,
                transaction_cost NUMERIC(18,4) NOT NULL,
                reason VARCHAR(120) NOT NULL,
                executed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );

            CREATE INDEX model_portfolio_positions_portfolio_idx
                ON model_portfolio_positions(portfolio_id);
            CREATE INDEX model_portfolio_transactions_portfolio_time_idx
                ON model_portfolio_transactions(portfolio_id, executed_at DESC);
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS model_portfolio_transactions;
            DROP TABLE IF EXISTS model_portfolio_positions;
            DROP TABLE IF EXISTS model_portfolio_runs;
            DROP TABLE IF EXISTS model_portfolios;
            SQL);
    }
};
