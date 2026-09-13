<?php

namespace App\Notifications;

use App\Services\RecommendationEmailChart;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Email;
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
        $isSale = $this->trade['action'] === 'sell';
        // Deliberately neutral wording (no "Kauf"/"Verkauf"): this is an automated
        // depot booking based on a model signal, not investment advice or a trade
        // recommendation - see resources/views/legal/show.blade.php §Risikohinweise.
        $subjectAction = $isSale ? __('Entfernt') : ($this->trade['action'] === 'increase' ? __('Aufgestockt') : __('Hinzugefügt'));
        $subjectPrefix = ($this->trade['simulation'] ?? false) ? __('aKI Simulation') : __('aKI Depot');
        $candles = (array) ($this->trade['candles'] ?? []);
        $chart = $candles !== []
            ? app(RecommendationEmailChart::class)->render($candles, $this->trade['target_price'] ?? null)
            : null;

        $mail = (new MailMessage)
            ->subject(__(':prefix · :action :symbol', [
                'prefix' => $subjectPrefix,
                'action' => $subjectAction,
                'symbol' => $this->trade['symbol'],
            ]))
            ->view('mail.portfolio-trade', [
                'trade' => $this->trade,
                'isSale' => $isSale,
                'depotUrl' => route('depots.show', $this->trade['portfolio_id']),
            ]);

        if ($chart !== null) {
            $mail->withSymfonyMessage(function (Email $email) use ($chart): void {
                $email->embed($chart, 'aki-trade-chart.png', 'image/png');
            });
        }

        return $mail;
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
