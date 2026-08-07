<?php

namespace App\Notifications;

use App\Models\Prediction;
use App\Models\SmartSelectionLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SmartSelectionSignalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Prediction $prediction,
        public readonly SmartSelectionLabel $strategy,
        public readonly string $previousSignal,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $instrument = $this->prediction->instrument;
        $signal = strtoupper((string) $this->prediction->signal);
        $locale = data_get($notifiable->preferences, 'locale', app()->getLocale());
        app()->setLocale(in_array($locale, ['de', 'en'], true) ? $locale : 'de');
        $theme = data_get($notifiable->preferences, 'theme', data_get($notifiable->preferences, 'color_scheme', 'light'));

        return (new MailMessage)
            ->subject(__('Neues Kaufsignal für :symbol', ['symbol' => $instrument->symbol]))
            ->markdown('mail.signal-changed', [
                'prediction' => $this->prediction,
                'instrument' => $instrument,
                'strategy' => $this->strategy,
                'recipientName' => $notifiable->name ?? null,
                'previousSignal' => strtoupper($this->previousSignal),
                'signal' => $signal,
                'expectedReturn' => $this->expectedReturn(),
                'emailTheme' => in_array($theme, ['dark', 'light'], true) ? $theme : 'light',
            ]);
    }

    private function expectedReturn(): ?float
    {
        $current = (float) ($this->prediction->current_price ?? 0);
        $target = (float) ($this->prediction->predicted_price_20d ?? 0);
        return $current > 0 && $target > 0 ? (($target / $current) - 1) * 100 : null;
    }
}
