<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PredictionPurchaseReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public object $reminder,
        public object $instrument,
        public float $currentPrice,
        public string $currentSignal = 'HOLD',
    ) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $return = (($this->currentPrice - (float) $this->reminder->purchase_price) / (float) $this->reminder->purchase_price) * 100;
        $interested = ($this->reminder->intent ?? 'purchased') === 'interested';
        $mail = (new MailMessage)
            ->subject(__($interested ? 'Kauferinnerung: :symbol · :days Tage' : 'Prognose-Auswertung: :symbol · :days Tage', ['symbol' => $this->instrument->symbol, 'days' => $this->reminder->horizon_days]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]))
            ->line($interested
                ? __('Du wolltest :name nach :days Tagen erneut als möglichen Kauf prüfen.', ['name' => $this->instrument->name, 'days' => $this->reminder->horizon_days])
                : __('Du hast den Kauf von :name für die :days-Tage-Prognose bestätigt.', ['name' => $this->instrument->name, 'days' => $this->reminder->horizon_days]))
            ->line(__($interested ? 'Beobachtungskurs: :price :currency' : 'Kaufkurs: :price :currency', ['price' => number_format((float) $this->reminder->purchase_price, 2, ',', '.'), 'currency' => $this->instrument->currency]))
            ->line(__('Aktueller Kurs: :price :currency', ['price' => number_format($this->currentPrice, 2, ',', '.'), 'currency' => $this->instrument->currency]))
            ->line(__('Entwicklung seitdem: :return %', ['return' => ($return > 0 ? '+' : '').number_format($return, 2, ',', '.')]))
            ->line(__('Aktuelles Signal: :signal', ['signal' => $this->currentSignal]));

        return $mail->action($interested ? __('Kauf jetzt prüfen') : __('Aktie ansehen'), route('stocks.show', $this->instrument->symbol))
            ->line(__('Dies ist eine persönliche Erinnerung und keine Anlageberatung.'));
    }
}
