<?php

namespace App\Notifications;

use App\Services\PredictionReminderChart;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

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
        $prediction = DB::table('predictions')->where('instrument_id', $this->instrument->id)
            ->orderByDesc('prediction_time')->orderByDesc('id')->first();
        $history = DB::table('price_bars')->where('instrument_id', $this->instrument->id)->where('interval', '1d')
            ->whereNotNull('close')->orderByDesc('bar_time')->limit(30)->pluck('close')->reverse()
            ->map(fn ($value): float => (float) $value)->values()->all();
        $forecasts = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($prediction): array {
            $column = "predicted_price_{$days}d";
            $target = is_numeric(data_get($prediction, $column)) ? (float) data_get($prediction, $column) : null;
            if ($target === null) {
                $target = DB::table('predictions')
                    ->where('instrument_id', $this->instrument->id)
                    ->where('prediction_horizon_minutes', $days * 1440)
                    ->whereNotNull($column)
                    ->orderByDesc('prediction_time')->orderByDesc('id')
                    ->value($column);
                $target = is_numeric($target) ? (float) $target : null;
            }

            return [$days => $target];
        })->all();
        $basePrice = max(.0001, (float) $this->reminder->purchase_price);
        $performance = (($this->currentPrice - $basePrice) / $basePrice) * 100;
        $logo = file_get_contents(public_path('brand/generated/bull-logo-light-clean.png'));
        $chart = app(PredictionReminderChart::class)->render($history, $forecasts);

        return (new MailMessage)
            ->subject(__('Kauferinnerung: :symbol · aktuelle Prognosen', ['symbol' => $this->instrument->symbol]))
            ->view('mail.prediction-purchase-reminder', [
                'user' => $notifiable, 'reminder' => $this->reminder, 'instrument' => $this->instrument,
                'currentPrice' => $this->currentPrice, 'currentSignal' => $this->currentSignal,
                'performance' => $performance, 'prediction' => $prediction, 'forecasts' => $forecasts,
                'stockUrl' => route('stocks.show', $this->instrument->symbol),
            ])
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($logo, $chart): void {
                $email->embed($logo, 'aktienki-logo.png', 'image/png');
                $email->embed($chart, 'prediction-chart.png', 'image/png');
            });
    }
}
