<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PortfolioTradeNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PortfolioTradeEmailService
{
    public function sendPending(int $limit = 100): array
    {
        $stats = ['checked' => 0, 'sent' => 0, 'failed' => 0, 'disabled' => 0];
        $ids = DB::table('portfolio_automation_executions')
            ->where('email_status', 'pending')
            ->whereIn('action', ['buy', 'increase', 'sell'])
            ->orderBy('id')->limit(max(1, min(1000, $limit)))->pluck('id');

        foreach ($ids as $id) {
            $stats['checked']++;
            $row = DB::transaction(function () use ($id): ?object {
                $execution = DB::table('portfolio_automation_executions as execution')
                    ->join('saved_prediction_filters as strategy', 'strategy.id', '=', 'execution.saved_prediction_filter_id')
                    ->join('users as user', 'user.id', '=', 'strategy.user_id')
                    ->join('portfolios as portfolio', 'portfolio.id', '=', 'execution.portfolio_id')
                    ->join('instruments as instrument', 'instrument.id', '=', 'execution.instrument_id')
                    ->leftJoin('portfolio_transactions as transaction', 'transaction.id', '=', 'execution.portfolio_transaction_id')
                    ->leftJoin('predictions as prediction', 'prediction.id', '=', 'execution.prediction_id')
                    ->where('execution.id', $id)
                    ->where('execution.email_status', 'pending')
                    ->lockForUpdate()
                    ->select([
                        'execution.id', 'execution.action', 'execution.position_factor', 'execution.allocated_capital',
                        'execution.sector_average_score', 'execution.created_at', 'strategy.name as strategy_name',
                        'strategy.user_id', 'portfolio.id as portfolio_id', 'portfolio.name as portfolio_name',
                        'portfolio.currency as portfolio_currency', 'portfolio.meta as portfolio_meta',
                        'instrument.symbol', 'instrument.name as instrument_name',
                        'instrument.currency as instrument_currency', 'instrument.sector', 'transaction.quantity',
                        'transaction.price', 'transaction.fees', 'transaction.transaction_date',
                        'transaction.meta as transaction_meta',
                        'prediction.predicted_price_20d', 'prediction.prediction_score', 'prediction.confidence',
                    ])->first();
                if (! $execution) return null;
                DB::table('portfolio_automation_executions')->where('id', $id)->update([
                    'email_status' => 'sending', 'updated_at' => now(),
                ]);
                return $execution;
            });
            if (! $row) continue;

            $user = User::query()->find($row->user_id);
            $portfolioMeta = is_string($row->portfolio_meta ?? null)
                ? (json_decode($row->portfolio_meta, true) ?: [])
                : (array) ($row->portfolio_meta ?? []);
            if (! $user
                || ! data_get($user->preferences, 'email_service', true)
                || ! data_get($portfolioMeta, 'automation.live_enabled', false)
                || ! data_get($portfolioMeta, 'automation.transaction_email_enabled', false)) {
                DB::table('portfolio_automation_executions')->where('id', $id)->update([
                    'email_status' => 'disabled', 'updated_at' => now(),
                ]);
                $stats['disabled']++;
                continue;
            }

            try {
                $user->notifyNow(new PortfolioTradeNotification((int) $id, $this->payload($row)));
                $stats['sent']++;
            } catch (Throwable $exception) {
                DB::table('portfolio_automation_executions')->where('id', $id)->update([
                    'email_status' => 'failed', 'email_failed_at' => now(),
                    'email_failure_message' => mb_substr($exception->getMessage(), 0, 2000), 'updated_at' => now(),
                ]);
                report($exception);
                $stats['failed']++;
            }
        }
        return $stats;
    }

    private function payload(object $row): array
    {
        $score = (float) ($row->prediction_score ?? 0);
        if ($score <= 1) $score *= 100;
        elseif ($score <= 10) $score *= 10;
        $confidence = (float) ($row->confidence ?? 0);
        if ($confidence <= 1) $confidence *= 100;
        $price = (float) ($row->price ?? 0);
        $target = (float) ($row->predicted_price_20d ?? 0);
        $transactionMeta = is_string($row->transaction_meta ?? null)
            ? (json_decode($row->transaction_meta, true) ?: [])
            : (array) ($row->transaction_meta ?? []);
        $cashBalance = (float) DB::table('portfolio_cash_accounts')
            ->where('portfolio_id', $row->portfolio_id)
            ->where('currency', $row->portfolio_currency)
            ->value('balance');
        $holdings = DB::table('portfolio_positions as position')
            ->join('instruments as instrument', 'instrument.id', '=', 'position.instrument_id')
            ->where('position.portfolio_id', $row->portfolio_id)
            ->where('position.quantity', '>', 0)
            ->orderByDesc(DB::raw('position.quantity * COALESCE(position.current_price, position.average_buy_price)'))
            ->get([
                'instrument.symbol', 'instrument.name', 'position.quantity',
                'position.average_buy_price', 'position.current_price',
            ])->map(function (object $position): array {
                $marketPrice = (float) ($position->current_price ?? $position->average_buy_price);
                return [
                    'symbol' => (string) $position->symbol,
                    'name' => (string) $position->name,
                    'quantity' => round((float) $position->quantity),
                    'market_price' => $marketPrice,
                    'value' => (float) $position->quantity * $marketPrice,
                ];
            })->all();
        $portfolioValue = array_sum(array_column($holdings, 'value'));
        $totalValue = $portfolioValue + $cashBalance;
        $portfolioMeta = is_string($row->portfolio_meta)
            ? (json_decode($row->portfolio_meta, true) ?: [])
            : (array) $row->portfolio_meta;
        $initialCapital = max(0.0, (float) data_get($portfolioMeta, 'automation.initial_capital', 0));
        $performance = $initialCapital > 0 ? (($totalValue / $initialCapital) - 1) * 100 : 0.0;

        return [
            'action' => (string) $row->action, 'symbol' => (string) $row->symbol,
            'instrument_name' => (string) $row->instrument_name,
            'portfolio_id' => (int) $row->portfolio_id, 'portfolio_name' => (string) $row->portfolio_name,
            'strategy_name' => (string) $row->strategy_name, 'sector' => (string) ($row->sector ?: '—'),
            'quantity' => (float) ($row->quantity ?? 0), 'price' => $price,
            'fees' => (float) ($row->fees ?? 0), 'allocated_capital' => (float) $row->allocated_capital,
            'currency' => (string) ($row->instrument_currency ?: $row->portfolio_currency),
            'transaction_date' => $row->transaction_date, 'position_factor' => (int) $row->position_factor,
            'score' => $score / 10, 'confidence' => $confidence,
            'target_price' => $target ?: null,
            'expected_return' => $price > 0 && $target > 0 ? (($target / $price) - 1) * 100 : null,
            'performance_percent' => $performance,
            'portfolio_value' => $portfolioValue,
            'cash_balance' => $cashBalance,
            'total_value' => $totalValue,
            'portfolio_currency' => (string) $row->portfolio_currency,
            'holdings' => $holdings,
            'realized_profit' => data_get($transactionMeta, 'realized_profit'),
            'transaction_performance_percent' => data_get($transactionMeta, 'performance_percent'),
        ];
    }
}
