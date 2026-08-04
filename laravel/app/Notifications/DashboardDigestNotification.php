<?php

namespace App\Notifications;

use App\Services\RecommendationEmailLogo;
use App\Services\DashboardEmailMap;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DashboardDigestNotification extends Notification
{
    public function __construct(public readonly array $dashboard) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = data_get($notifiable->preferences, 'locale', 'de');
        app()->setLocale(in_array($locale, ['de', 'en'], true) ? $locale : 'de');
        $logo = app(RecommendationEmailLogo::class)->render();
        $map = app(DashboardEmailMap::class)->render((array) ($this->dashboard['countryChanges'] ?? []));

        return (new MailMessage)
            ->subject('aKI Dashboard · Aktuelle Marktsituation')
            ->markdown('mail.dashboard-digest', $this->dashboard)
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($logo, $map): void {
                $email->embed($logo, 'aktienki-logo.png', 'image/png');
                $email->embed($map, 'aki-market-map.png', 'image/png');
            });
    }
}
