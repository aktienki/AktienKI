<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TodayHighlightsAnalysisService
{
    private const INSTRUCTIONS = <<<'PROMPT'
Du analysierst vier Highlights des heutigen Handelstags für einen Investor. Jedes Highlight ist eine Aktie mit einem spezifischen Ereignis (beste BUY-Empfehlung, grösste Performance-Bewegung, überraschender Signalwechsel, Trend-Umkehr).

Gib für jedes Highlight EINE prägnante Analysezeile (max 15 Wörter): was ist das Signal, und warum ist es relevant?

Antworte nur mit einem JSON-Objekt mit vier Feldern: top_signal_insight, swing_insight, surprise_insight, trend_switch_insight. Keine Erklärungen, nur die Insights.
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
                            'top_signal' => $highlights[0]['data'] ?? null,
                            'swing' => $highlights[1]['data'] ?? null,
                            'surprise' => $highlights[2]['data'] ?? null,
                            'trend_switch' => $highlights[3]['data'] ?? null,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
                // Reasoning models (e.g. Kimi) spend a large share of this
                // budget on their internal reasoning trace before writing
                // the actual JSON answer - too low a limit truncates the
                // response to nothing before it gets there.
                'max_tokens' => 1500,
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
                ->timeout(30)
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
