<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TodayHighlightsAnalysisService
{
    private const INSTRUCTIONS = <<<'PROMPT'
Du analysierst vier Highlights des heutigen Handelstags für einen Investor. Jedes Highlight ist eine Aktie mit einem spezifischen Ereignis (beste BUY-Empfehlung, grösste Performance-Bewegung, überraschender Signalwechsel, Trend-Umkehr). Zu jedem Highlight bekommst du Kennzahlen (Sektor, Kurs, Risiko, Konfidenz) und, falls vorhanden, einen historischen Vergleichsfall mit tatsächlichem Ergebnis.

Schreibe für jedes Highlight eine fundierte Analyse aus 3-4 Sätzen (ca. 50-70 Wörter): was ist das Signal, warum ist es relevant, was sagen die Kennzahlen (Sektor, Risiko, Konfidenz) darüber aus, und falls ein historischer Vergleichsfall vorliegt, wie ordnet der das Signal ein? Schreibe konkret und sachlich, keine Floskeln, keine Anlageberatung.

Antworte nur mit einem JSON-Objekt mit vier Feldern: top_signal_insight, swing_insight, surprise_insight, trend_switch_insight. Keine Erklärungen ausserhalb der Felder.
PROMPT;

    public function analyzeHighlights(array $highlights): array
    {
        $apiKey = trim((string) config('aktienki.stock_ai_assessment.grid_api_key'));
        if ($apiKey === '') {
            return $this->defaultHighlights($highlights);
        }

        try {
            $payload = [
                'model' => (string) config('aktienki.stock_ai_assessment.grid_model', 'text-standard'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => self::INSTRUCTIONS,
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'top_signal' => $this->context($highlights[0] ?? null),
                            'swing' => $this->context($highlights[1] ?? null),
                            'surprise' => $this->context($highlights[2] ?? null),
                            'trend_switch' => $this->context($highlights[3] ?? null),
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
                // Reasoning models (e.g. Kimi) spend a large, variable share
                // of this budget on an internal reasoning trace before
                // writing the actual answer; four 3-4 sentence analyses
                // need real headroom on top of that.
                'max_tokens' => 3000,
            ];

            $endpoint = (string) config('aktienki.stock_ai_assessment.grid_endpoint', 'https://api.thegrid.ai/v1/chat/completions');
            // The Grid's backend load-balances across providers; roughly
            // one in three requests transiently 500s/400s on a provider
            // that can't serve this model right now. A short retry almost
            // always lands on a working one.
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(45)
                ->retry(3, 1500, throw: false)
                ->post($endpoint, $payload);

            if ($response->failed()) {
                return $this->defaultHighlights($highlights);
            }

            $text = (string) data_get($response->json(), 'choices.0.message.content', '');
            // Some models (e.g. Kimi) wrap their JSON answer in a markdown
            // code fence despite being told to answer with JSON only.
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text));
            $insights = json_decode($text, true) ?? [];

            return [
                'top_signal_insight' => (string) ($insights['top_signal_insight'] ?? ''),
                'swing_insight' => (string) ($insights['swing_insight'] ?? ''),
                'surprise_insight' => (string) ($insights['surprise_insight'] ?? ''),
                'trend_switch_insight' => (string) ($insights['trend_switch_insight'] ?? ''),
            ];
        } catch (Exception $e) {
            return $this->defaultHighlights($highlights);
        }
    }

    /** Reduces one highlight to the context worth sending to the model - its data point plus the details/analog enrichment already computed for the card. */
    private function context(?array $highlight): ?array
    {
        if (! $highlight || ! $highlight['data']) {
            return null;
        }

        $details = $highlight['details'] ?? null;
        $analog = $highlight['analog'] ?? null;

        return array_filter([
            'data' => $highlight['data'],
            'metric' => $highlight['metric_value'] ?? null,
            'sector' => $details['sector'] ?? null,
            'country' => $details['country'] ?? null,
            'current_price' => $details['current_price'] ?? null,
            'risk_1_to_10' => $details['risk'] ?? null,
            'confidence_percent' => $details['confidence'] ?? null,
            'historical_analog' => $analog ? [
                'compared_to' => $analog['analog_symbol'],
                'days_ago' => (int) round(\Illuminate\Support\Carbon::parse($analog['signal_date'])->diffInDays(now())),
                'actual_outcome_percent' => $analog['outcome_pct'],
            ] : null,
        ], fn ($value) => $value !== null);
    }

    private function defaultHighlights(array $highlights): array
    {
        return [
            'top_signal_insight' => $highlights[0]['data'] ?? 'Keine Daten',
            'swing_insight' => $highlights[1]['data'] ?? 'Keine Daten',
            'surprise_insight' => $highlights[2]['data'] ?? 'Keine Daten',
            'trend_switch_insight' => $highlights[3]['data'] ?? 'Keine Daten',
        ];
    }
}
