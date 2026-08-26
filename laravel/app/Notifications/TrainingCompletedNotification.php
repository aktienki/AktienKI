<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TrainingCompletedNotification extends Notification
{
    public function __construct(public readonly array $report) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $symbol = (string) ($this->report['symbol'] ?? 'Aktie');

        return (new MailMessage)
            ->subject("aKI Training abgeschlossen · {$symbol}")
            ->markdown('mail.training-completed', $this->report);
    }
}
