<?php

namespace App\Notifications;

use App\Services\RecommendationEmailLogo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PublicPortfolioTradeNotification extends Notification
{
    public function __construct(public readonly array $trade) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $locale = data_get($notifiable->preferences, 'locale', 'de');
        app()->setLocale(in_array($locale, ['de', 'en'], true) ? $locale : 'de');
        $logo = app(RecommendationEmailLogo::class)->render();
        $action = $this->trade['type'] === 'sell' ? __('Verkauf') : __('Kauf');
        return (new MailMessage)
            ->subject(__('Musterdepot :portfolio · :action :symbol', ['portfolio'=>$this->trade['portfolio_name'], 'action'=>$action, 'symbol'=>$this->trade['symbol']]))
            ->view('mail.public-portfolio-trade', ['trade'=>$this->trade, 'action'=>$action, 'depotUrl'=>route('depots.show', $this->trade['portfolio_id'])])
            ->withSymfonyMessage(fn (\Symfony\Component\Mime\Email $email) => $email->embed($logo, 'aktienki-logo.png', 'image/png'));
    }
}
