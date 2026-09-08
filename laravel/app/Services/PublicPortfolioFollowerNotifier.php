<?php

namespace App\Services;

use App\Models\PortfolioTransaction;
use App\Models\User;
use App\Notifications\PublicPortfolioTradeNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PublicPortfolioFollowerNotifier
{
    public function send(int $transactionId): void
    {
        $transaction = PortfolioTransaction::query()->with(['portfolio', 'instrument'])->find($transactionId);
        if (! $transaction || ! $transaction->portfolio?->is_public_readonly || ! in_array($transaction->type, ['buy','sell'], true)) return;
        $trade = ['portfolio_id'=>$transaction->portfolio_id, 'portfolio_name'=>$transaction->portfolio->name,
            'type'=>$transaction->type, 'symbol'=>$transaction->instrument?->symbol ?: '—',
            'name'=>$transaction->instrument?->name ?: $transaction->instrument?->symbol ?: '—',
            'quantity'=>$transaction->quantity, 'price'=>$transaction->price, 'fees'=>$transaction->fees,
            'currency'=>$transaction->currency, 'date'=>$transaction->transaction_date];
        User::query()->whereIn('id', DB::table('portfolio_followers')->where('portfolio_id', $transaction->portfolio_id)
            ->where('email_enabled', true)->pluck('user_id'))->get()->each(function (User $user) use ($trade): void {
                try { $user->notifyNow(new PublicPortfolioTradeNotification($trade)); } catch (Throwable $e) { report($e); }
            });
    }
}
