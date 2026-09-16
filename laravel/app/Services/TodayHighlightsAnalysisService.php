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
                'max_tokens' => 300,
            ];

            $endpoint = (string) config('aktienki.stock_ai_assessment.grid_endpoint', 'https://api.thegrid.ai/v1/chat/completions');
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(30)
                ->post($endpoint, $payload);

            if ($response->failed()) {
                return $this->defaultHighlights($highlights);
            }

            $text = (string) data_get($response->json(), 'choices.0.message.content', '');
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
