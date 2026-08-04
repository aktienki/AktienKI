<?php

namespace App\Notifications;

use App\Services\RecommendationEmailLogo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PortfolioTradeNotification extends Notification
{
    public function __construct(
        public readonly int $executionId,
        public readonly array $trade,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = data_get($notifiable->preferences, 'locale', 'de');
        app()->setLocale(in_array($locale, ['de', 'en'], true) ? $locale : 'de');
        $logo = app(RecommendationEmailLogo::class)->render();
        $isSale = $this->trade['action'] === 'sell';
        $subjectAction = $isSale ? __('Verkauf') : ($this->trade['action'] === 'increase' ? __('Position aufgestockt') : __('Kauf'));
        $subjectPrefix = ($this->trade['simulation'] ?? false) ? __('aKI Simulation') : __('aKI Depot');

        return (new MailMessage)
            ->subject(__(':prefix · :action :symbol', [
                'prefix' => $subjectPrefix,
                'action' => $subjectAction,
                'symbol' => $this->trade['symbol'],
            ]))
            ->view('mail.portfolio-trade', [
                'trade' => $this->trade,
                'isSale' => $isSale,
                'depotUrl' => route('depots.show', $this->trade['portfolio_id']),
            ])
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($logo): void {
                $email->embed($logo, 'aktienki-logo.png', 'image/png');
            });
    }

    public function failed(Throwable $exception): void
    {
        DB::table('portfolio_automation_executions')->where('id', $this->executionId)->update([
            'email_status' => 'failed',
            'email_failed_at' => now(),
            'email_failure_message' => mb_substr($exception->getMessage(), 0, 2000),
            'updated_at' => now(),
        ]);
    }
}
