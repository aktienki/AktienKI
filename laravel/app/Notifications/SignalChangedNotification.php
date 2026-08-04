<?php

namespace App\Notifications;

use App\Models\Prediction;
use App\Models\SavedPredictionFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignalChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Prediction $prediction,
        public readonly SavedPredictionFilter $strategy,
        public readonly string $previousSignal,
        public readonly int $deliveryId,
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

        return (new MailMessage)
            ->subject(__('Neues aKI-Signal für :symbol', ['symbol' => $instrument->symbol]))
            ->markdown('mail.signal-changed', [
                'prediction' => $this->prediction,
                'instrument' => $instrument,
                'strategy' => $this->strategy,
                'previousSignal' => strtoupper($this->previousSignal),
                'signal' => $signal,
                'expectedReturn' => $this->expectedReturn(),
            ]);
    }

    public function failed(\Throwable $exception): void
    {
        \App\Models\SignalEmailDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
    }

    private function expectedReturn(): ?float
    {
        $value = $this->prediction->long_return_20d;
        if ($value === null && $this->prediction->current_price && $this->prediction->predicted_price_20d) {
            return (((float) $this->prediction->predicted_price_20d / (float) $this->prediction->current_price) - 1) * 100;
        }

        if ($value === null) return null;
        return abs((float) $value) <= 1 ? (float) $value * 100 : (float) $value;
    }
}
