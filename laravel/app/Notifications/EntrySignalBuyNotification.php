<?php

namespace App\Notifications;

use App\Models\Prediction;
use App\Services\RecommendationEmailChart;
use App\Support\AiScore;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Part\DataPart;

class EntrySignalBuyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Prediction $prediction, public readonly string $signal = 'BUY') {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = data_get($notifiable->preferences, 'locale', 'de');
        app()->setLocale(in_array($locale, ['de', 'en'], true) ? $locale : 'de');
        $instrument = $this->prediction->instrument;
        $candles = DB::table('price_bars')
            ->where('instrument_id', $instrument->id)
            ->where('interval', '1d')
            ->orderByDesc('bar_time')
            ->limit(32)
            ->get(['bar_time', 'open', 'high', 'low', 'close'])
            ->reverse()
            ->values()
            ->map(fn (object $bar): array => [
                'x' => \Illuminate\Support\Carbon::parse($bar->bar_time)->getTimestampMs(),
                'y' => [(float) $bar->open, (float) $bar->high, (float) $bar->low, (float) $bar->close],
            ])->all();
        $horizonRows = DB::table('predictions')
            ->where('instrument_id', $instrument->id)
            ->whereIn('prediction_horizon_minutes', [7200, 14400, 21600, 28800])
            ->orderByDesc('prediction_time')->orderByDesc('id')->get()
            ->unique('prediction_horizon_minutes')->mapWithKeys(function (object $row): array {
                $days = (int) round(((int) $row->prediction_horizon_minutes) / 1440);
                $targetField = 'predicted_price_'.$days.'d';
                $target = is_numeric($row->{$targetField} ?? null) ? (float) $row->{$targetField} : null;
                $current = (float) ($row->current_price ?? 0);
                return [$days => [
                    'days' => $days,
                    'target' => $target,
                    'return' => $target !== null && $current > 0 ? (($target / $current) - 1) * 100 : null,
                    'score' => AiScore::toTen($row->ai_score ?? $row->prediction_score ?? null),
                    'signal' => strtoupper((string) ($row->signal ?? 'HOLD')),
                ]];
            })->sortKeys()->all();
        $forecasts = collect($horizonRows)->pluck('target', 'days')->filter(fn ($value) => $value !== null)->all();
        $chart = app(RecommendationEmailChart::class)->renderForecasts($candles, $forecasts);
        $assessment = DB::table('stock_ai_assessments')->where('instrument_id', $instrument->id)
            ->latest('assessment_date')->latest('id')->first();
        $assessmentSummary = $assessment?->summary ?: ($this->prediction->quality_gate_explanation
            ?: __('Das Modell bewertet :name derzeit mit dem Signal :signal. Die Prognosen der einzelnen Zeithorizonte sollten gemeinsam betrachtet werden.', ['name' => $instrument->name, 'signal' => strtoupper($this->signal)]));
        $assessmentSummary = collect(preg_split('/(?<=[.!?])\s+|[,;]\s+/u', $assessmentSummary) ?: [])
            ->reject(fn (string $part): bool => preg_match('/\b(?:Konfidenz|confidence|Risiko\w*|risk\w*)\b/iu', $part) === 1)
            ->map(fn (string $part): string => trim($part))
            ->filter()->implode(' ');

        return (new MailMessage)
            ->subject(__('Einstiegsstatus für :symbol: :signal', ['symbol' => $instrument->symbol, 'signal' => $this->signal]))
            ->markdown('mail.entry-signal', [
                'recipientName' => $notifiable->name,
                'instrument' => $instrument,
                'prediction' => $this->prediction,
                'signal' => strtoupper($this->signal),
                'horizons' => $horizonRows,
                'assessment' => $assessment,
                'assessmentSummary' => $assessmentSummary,
            ])
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($chart): void {
                $chartPart = (new DataPart($chart, 'aki-entry-signal-chart.png', 'image/png'))->asInline();
                $chartPart->setContentId('aki-entry-signal-chart@aktienki.com');
                $email->addPart($chartPart);
            });
    }
}
