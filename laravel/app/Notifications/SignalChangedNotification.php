<?php

namespace App\Notifications;

use App\Models\ExternalBuyReview;
use App\Models\Prediction;
use App\Models\SavedPredictionFilter;
use App\Models\SignalEmailDelivery;
use App\Models\User;
use App\Enums\PlanLevel;
use App\Services\PlanAccessService;
use App\Services\SignalEmailDonutChart;
use App\Services\SignalEmailMetrics;
use App\Support\CountryFlag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

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
        $theme = data_get($notifiable->preferences, 'theme', data_get($notifiable->preferences, 'color_scheme', 'light'));
        $emailTheme = in_array($theme, ['dark', 'light'], true) ? $theme : 'light';
        $metrics = app(SignalEmailMetrics::class)->forPrediction($this->prediction);
        $donutChart = app(SignalEmailDonutChart::class)->render($metrics, $emailTheme === 'dark');
        $canViewExternalReview = $notifiable instanceof User
            && app(PlanAccessService::class)->allowsTariff($notifiable, PlanLevel::Pro);
        $externalReview = $signal === 'BUY' && $canViewExternalReview
            ? ExternalBuyReview::query()->where('prediction_id', $this->prediction->id)->first()
            : null;

        return (new MailMessage)
            ->subject(__('Neues aKI-Signal für :symbol', ['symbol' => $instrument->symbol]))
            ->markdown('mail.signal-changed', [
                'prediction' => $this->prediction,
                'instrument' => $instrument,
                'strategy' => $this->strategy,
                'recipientName' => $notifiable->name ?? null,
                'previousSignal' => strtoupper($this->previousSignal),
                'signal' => $signal,
                'emailTheme' => $emailTheme,
                'countryFlag' => CountryFlag::emoji($instrument->country),
                'signalMetrics' => $metrics,
                'externalReview' => $externalReview,
            ])
            ->withSymfonyMessage(function (Email $email) use ($donutChart): void {
                $email->embed($donutChart, 'aki-signal-score-risk.png', 'image/png');
            });
    }

    public function failed(\Throwable $exception): void
    {
        SignalEmailDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
    }
}
