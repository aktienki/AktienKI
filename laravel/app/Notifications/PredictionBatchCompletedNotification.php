<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PredictionBatchCompletedNotification extends Notification
{
    public function __construct(public readonly array $report) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('AktienKI · Prediction-Batch abgeschlossen')
            ->markdown('mail.prediction-batch-completed', $this->report);
    }
}
