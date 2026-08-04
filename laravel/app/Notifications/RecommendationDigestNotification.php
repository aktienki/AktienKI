<?php

namespace App\Notifications;

use App\Services\RecommendationEmailChart;
use App\Services\RecommendationEmailLogo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

final class RecommendationDigestNotification extends Notification
{
    public function __construct(public readonly array $recommendations) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = data_get($notifiable->preferences, 'locale', 'de');
        app()->setLocale(in_array($locale, ['de', 'en'], true) ? $locale : 'de');

        $items = collect($this->recommendations)->take(5)->values()->map(function (array $item, int $index): array {
            $assessment = DB::table('stock_ai_assessments')
                ->where('instrument_id', (int) ($item['instrument_id'] ?? 0))
                ->when(! empty($item['prediction_id']), fn ($query) => $query->where('prediction_id', (int) $item['prediction_id']))
                ->latest('assessment_date')->latest('id')->first();

            return array_merge($item, [
                'chart_cid' => 'aki-recommendation-chart-'.($index + 1).'.png',
                'analysis' => $assessment?->summary,
                'analysis_risks' => $this->decodeList($assessment?->risks),
            ]);
        })->all();

        $charts = collect($items)->mapWithKeys(fn (array $item): array => [
            $item['chart_cid'] => app(RecommendationEmailChart::class)->render(
                (array) ($item['candles'] ?? []),
                $item['target_price'] ?? null,
            ),
        ])->all();
        $logo = app(RecommendationEmailLogo::class)->render();

        return (new MailMessage)
            ->subject('Aktuelle Signale bei AktienKI.com')
            ->markdown('mail.recommendation-digest', ['recommendations' => $items])
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($charts, $logo): void {
                $email->embed($logo, 'aktienki-logo.png', 'image/png');
                foreach ($charts as $name => $chart) $email->embed($chart, $name, 'image/png');
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
