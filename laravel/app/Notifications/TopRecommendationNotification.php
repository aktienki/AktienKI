<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Services\RecommendationEmailChart;
use App\Services\RecommendationEmailLogo;
use Illuminate\Support\Facades\DB;

final class TopRecommendationNotification extends Notification
{
    public function __construct(public readonly array $recommendation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = data_get($notifiable->preferences, 'locale', 'de');
        app()->setLocale(in_array($locale, ['de', 'en'], true) ? $locale : 'de');

        $assessment = DB::table('stock_ai_assessments')
            ->where('instrument_id', (int) ($this->recommendation['instrument_id'] ?? 0))
            ->when(! empty($this->recommendation['prediction_id']), fn ($query) =>
                $query->where('prediction_id', (int) $this->recommendation['prediction_id']))
            ->latest('assessment_date')->latest('id')->first();
        $mailData = array_merge($this->recommendation, [
            'analysis' => $assessment?->summary,
            'analysis_recommendation' => $assessment?->recommendation,
            'analysis_confidence' => $assessment?->confidence,
            'analysis_opportunities' => $this->decodeList($assessment?->opportunities),
            'analysis_risks' => $this->decodeList($assessment?->risks),
            'analysis_factors' => $this->decodeList($assessment?->key_factors),
        ]);
        $chart = app(RecommendationEmailChart::class)->render(
            (array) ($this->recommendation['candles'] ?? []),
            $this->recommendation['target_price'] ?? null,
        );
        $logo = app(RecommendationEmailLogo::class)->render();

        return (new MailMessage)
            ->subject(__('aKI Top-Empfehlung: :symbol', ['symbol' => $this->recommendation['symbol']]))
            ->markdown('mail.top-recommendation', $mailData)
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($chart, $logo): void {
                $email->embed($chart, 'aki-recommendation-chart.png', 'image/png');
                $email->embed($logo, 'aktienki-logo.png', 'image/png');
            });
    }

    private function decodeList(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (! is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }
}
