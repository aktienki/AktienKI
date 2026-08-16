<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BetaTrialExpiryReminder extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $endsAt = $notifiable->tariff_ends_at?->timezone($notifiable->timezone ?: 'Europe/Berlin')->format('d.m.Y');

        return (new MailMessage)
            ->subject(__('Dein kostenloser AktienKI-Pro-Zugang endet bald'))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]))
            ->line(__('Dein kostenloses Pro-Jahr für den Betatest endet am :date.', ['date' => $endsAt ?: __('bald')]))
            ->line(__('Ab diesem Zeitpunkt ist der Pro-Tarif kostenpflichtig. Du kannst dein Abo vorher prüfen oder ändern.'))
            ->action(__('Abo ansehen'), route('pricing'))
            ->line(__('Wenn du nichts ändern möchtest, musst du nichts tun. Diese Nachricht dient als rechtzeitige Erinnerung.'));
    }
}
