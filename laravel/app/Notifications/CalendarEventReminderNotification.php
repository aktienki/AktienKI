<?php

namespace App\Notifications;

use App\Models\CalendarEventReminder;
use App\Models\Instrument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalendarEventReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly CalendarEventReminder $reminder,
        public readonly Instrument $instrument,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isEarnings = $this->reminder->event_type === 'earnings';
        $eventDate = $this->reminder->event_date->format('d.m.Y');
        $symbol = $this->instrument->symbol;

        return (new MailMessage)
            ->subject($isEarnings
                ? __('Erinnerung: Quartalszahlen :symbol morgen', ['symbol' => $symbol])
                : __('Erinnerung: Geplanter Verkauf :symbol heute', ['symbol' => $symbol]))
            ->greeting($isEarnings
                ? __('Quartalszahlen-Erinnerung')
                : __('Verkaufs-Erinnerung'))
            ->line($isEarnings
                ? __(':name (:symbol) meldet morgen (:date) Quartalszahlen.', ['name' => $this->instrument->name, 'symbol' => $symbol, 'date' => $eventDate])
                : __('Für :name (:symbol) ist heute (:date) ein geplanter Verkauf vorgesehen.', ['name' => $this->instrument->name, 'symbol' => $symbol, 'date' => $eventDate]))
            ->action(__('Zur Aktie'), route('stocks.show', ['symbol' => $symbol]));
    }
}
