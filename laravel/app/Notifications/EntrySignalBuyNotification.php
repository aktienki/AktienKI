<?php

namespace App\Notifications;

use App\Models\Prediction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntrySignalBuyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Prediction $prediction, public readonly string $signal = 'BUY') {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $instrument = $this->prediction->instrument;

        return (new MailMessage)
            ->subject(__('Einstiegsstatus für :symbol: :signal', ['symbol' => $instrument->symbol, 'signal' => $this->signal]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]))
            ->line($this->signal === 'BUY'
                ? __('Der zuvor beobachtete Status WAIT hat für :name (:symbol) auf BUY gewechselt.', ['name' => $instrument->name, 'symbol' => $instrument->symbol])
                : __('Der Status für :name (:symbol) ist weiterhin WAIT. Der langfristige Ausblick bleibt positiv, kurzfristig wird weiter abgewartet.', ['name' => $instrument->name, 'symbol' => $instrument->symbol]))
            ->line(__('Aktueller Kurs: :price :currency', ['price' => number_format((float) $this->prediction->current_price, 2, ',', '.'), 'currency' => $instrument->currency]))
            ->action(__('Aktie ansehen'), route('stocks.show', $instrument->symbol))
            ->line(__('Dies ist ein Modellsignal und keine Anlageberatung.'));
    }
}
