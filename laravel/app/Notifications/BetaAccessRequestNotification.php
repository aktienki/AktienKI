<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BetaAccessRequestNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ContactMessage $requestMessage) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Neue Anfrage für den AktienKI-Betatest')
            ->greeting('Neue Beta-Anfrage')
            ->line('Name: '.$this->requestMessage->name)
            ->line('E-Mail: '.$this->requestMessage->email)
            ->line('Motivation:')
            ->line($this->requestMessage->message)
            ->action('Anfrage prüfen & Code senden', route('beta.requests.review', $this->requestMessage))
            ->line('Die Anfrage wurde als neu in den Kontaktnachrichten gespeichert.');
    }
}
