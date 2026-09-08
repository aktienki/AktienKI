<?php

namespace App\Notifications;

use App\Services\PredictionReminderChart;
use App\Services\ServingChartCacheService;
use App\Services\ServingReadService;
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
        $stock = app(ServingReadService::class)->stock($this->instrument->symbol);
        abort_unless($stock, 404);
        $prediction = $stock->latest_prediction;
        $forecasts = collect([10, 20, 40])->mapWithKeys(fn (int $days): array => [
            $days => is_numeric($stock->horizons->get($days)?->prediction?->target_price)
                ? (float) $stock->horizons->get($days)->prediction->target_price : null,
        ])->all();
        $charts = app(ServingChartCacheService::class);
        $providerSymbol = $charts->providerSymbol($stock);
        $chartPayload = $charts->peek((int) $stock->instrument_id, $providerSymbol)
            ?? $charts->load((int) $stock->instrument_id, $providerSymbol, (string) $stock->currency);
        $history = collect($chartPayload['points'] ?? [])->take(-30)->pluck('close')->map(fn ($value): float => (float) $value)->all();
        $basePrice = max(.0001, (float) $this->reminder->purchase_price);
        $performance = (($this->currentPrice - $basePrice) / $basePrice) * 100;
        $chart = app(PredictionReminderChart::class)->render($history, $forecasts);

        $purchased = (string) ($this->reminder->intent ?? 'interested') === 'purchased';

        return (new MailMessage)
            ->subject($purchased
                ? __('Positions-Check: :symbol · Entwicklung und Prognosen', ['symbol' => $this->instrument->symbol])
                : __('Kauf-Check: :symbol · Signal erneut prüfen', ['symbol' => $this->instrument->symbol]))
            ->markdown('mail.prediction-purchase-reminder', [
                'user' => $notifiable, 'reminder' => $this->reminder, 'instrument' => $this->instrument,
                'currentPrice' => $this->currentPrice, 'currentSignal' => $this->currentSignal,
                'performance' => $performance, 'prediction' => $prediction, 'forecasts' => $forecasts,
                'stockUrl' => route('stocks.show', $this->instrument->symbol), 'purchased' => $purchased,
            ])
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($chart): void {
                $email->embed($chart, 'prediction-chart.png', 'image/png');
            });
    }
}
