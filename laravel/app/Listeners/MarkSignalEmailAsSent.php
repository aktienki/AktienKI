<?php

namespace App\Listeners;

use App\Models\SignalEmailDelivery;
use App\Notifications\SignalChangedNotification;
use App\Notifications\PortfolioTradeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\Events\NotificationSent;

class MarkSignalEmailAsSent
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') return;

        if ($event->notification instanceof PortfolioTradeNotification) {
            DB::table('portfolio_automation_executions')->where('id', $event->notification->executionId)->update([
                'email_status' => 'sent', 'email_sent_at' => now(),
                'email_failed_at' => null, 'email_failure_message' => null, 'updated_at' => now(),
            ]);
            return;
        }

        if (! $event->notification instanceof SignalChangedNotification) return;

        SignalEmailDelivery::query()->whereKey($event->notification->deliveryId)->update([
            'status' => 'sent',
            'sent_at' => now(),
            'failure_message' => null,
        ]);
    }
}
