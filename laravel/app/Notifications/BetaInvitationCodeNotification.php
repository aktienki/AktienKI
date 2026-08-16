<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BetaInvitationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly string $registrationUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dein persönlicher Zugang zur AktienKI-Beta')
            ->greeting('Willkommen bei der AktienKI-Beta!')
            ->line('Deine Anfrage wurde geprüft und dein Platz ist bestätigt.')
            ->line('Dein persönlicher Registrierungscode lautet:')
            ->line($this->code)
            ->action('Jetzt als Betatester registrieren', $this->registrationUrl)
            ->line('Der Code ist einmalig nutzbar. Wir freuen uns auf deine aktive Mitarbeit und deine abschließende Bewertung.')
            ->line('Als Dankeschön erhältst du nach der Beta ein Jahr Pro kostenlos.');
    }
}
