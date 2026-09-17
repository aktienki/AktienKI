<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Writes a short opportunities/risks/key-factors assessment for a POSITIV
 * stock from data AktienKI already has (composite score, quality gate,
 * expected return, risk, panel rank) - a plain writing task with no need
 * for live web research, unlike ExternalBuyReviewService. The Grid's spot
 * market (a general-purpose LLM aggregator) is a cheap, good fit for this.
 */
final class StockAiAssessmentService
{
    private const RECOMMENDATIONS = ['BUY', 'HOLD', 'SELL'];

    private const INSTRUCTIONS = <<<'PROMPT'
Du fasst eine bereits getroffene AktienKI-Modellentscheidung für einen Nutzer in Worten zusammen. Die mitgelieferten Kennzahlen (Signal, Composite-Score, Quality-Gate, erwartete Rendite, Risiko, Panel-Rang) sind das Ergebnis des eigenen ML-Modells - du triffst keine neue Anlageentscheidung und recherchierst nichts extern, sondern erklärst nur, was diese Zahlen bedeuten und worauf sie hindeuten.

Leite aus den Kennzahlen eine kurze, sachliche Einordnung ab: eine knappe Zusammenfassung, 2-4 Chancen, 2-4 Risiken und 2-4 Schlüsselfaktoren, die die Bewertung erklären. Erfinde keine Fakten oder externen Ereignisse, die nicht aus den Kennzahlen ableitbar sind. Antworte auf Deutsch, sachlich und knapp. Das Ergebnis ist eine Erklärung des Modellergebnisses, keine Anlageberatung.
PROMPT;

    public function generate(object $stock): array
    {
        $apiKey = trim((string) config('aktienki.stock_ai_assessment.grid_api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('GRID_API_KEY ist für die KI-Einordnung nicht konfiguriert.');
        }

        $input = $this->stockContext($stock);
        $model = (string) config('aktienki.stock_ai_assessment.grid_model', 'text-standard');
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => self::INSTRUCTIONS."\n\nAntworte ausschließlich mit einem einzigen JSON-Objekt gemäß dem vorgegebenen Schema - kein Fließtext davor oder danach.",
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['schema' => $this->resultSchema()],
            ],
            'max_tokens' => max(400, (int) config('aktienki.stock_ai_assessment.max_output_tokens', 900)),
        ];

        $endpoint = (string) config('aktienki.stock_ai_assessment.grid_endpoint', 'https://api.thegrid.ai/v1/chat/completions');
        // The Grid's backend load-balances across providers; roughly one in
        // three requests using response_format/tools transiently 500s or
        // 400s on a provider that doesn't support the requested feature.
        // A short retry almost always lands on a working provider.
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(15)
                ->timeout(120)
                ->retry(3, 1500)
                ->post($endpoint, $payload);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new RuntimeException($this->providerError($e->response));
        }

        $rawResponse = $response->json();
        if (! is_array($rawResponse)) {
            throw new RuntimeException('The Grid lieferte keine gültige JSON-Antwort.');
        }

        $text = (string) data_get($rawResponse, 'choices.0.message.content', '');
        $result = $this->decodeResult($text);

        return [
            'result' => $result,
            'model' => (string) ($rawResponse['model'] ?? $model),
            'input_snapshot' => $input,
            'raw_response' => $rawResponse,
        ];
    }

    /** @return array<string, mixed> */
    private function stockContext(object $stock): array
    {
        return array_filter([
            'symbol' => $stock->symbol ?? null,
            'name' => $stock->name ?? null,
            'country' => $stock->country ?? null,
            'sector' => $stock->sector ?? null,
            'signal' => $stock->personalized_signal ?? $stock->model_signal ?? null,
            'trigger_model_name' => $stock->trigger_model_name ?? null,
            'trigger_model_horizon_days' => $stock->trigger_model_horizon ?? null,
            'trigger_model_expected_return_percent' => $stock->trigger_model_expected_return_percent ?? null,
            'trigger_model_quality_gate_passed' => $stock->trigger_model_quality_gate_passed ?? null,
            'composite_score' => $stock->composite_score ?? null,
            'profit_factor' => $stock->ranking_profit_factor ?? null,
            'hit_rate_percent' => $stock->ranking_hit_rate ?? null,
            'confidence_percent' => $stock->confidence_percent ?? null,
            'risk_percent' => $stock->risk_percent ?? null,
            'panel_percentile' => $stock->panel_percentile ?? null,
            'panel_decile' => $stock->panel_decile ?? null,
        ], static fn ($value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    private function resultSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['recommendation', 'confidence', 'summary', 'opportunities', 'risks', 'key_factors'],
            'properties' => [
                'recommendation' => ['type' => 'string', 'enum' => self::RECOMMENDATIONS],
                'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'summary' => ['type' => 'string', 'maxLength' => 600],
                'opportunities' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 4, 'items' => ['type' => 'string', 'maxLength' => 250]],
                'risks' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 4, 'items' => ['type' => 'string', 'maxLength' => 250]],
                'key_factors' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 4, 'items' => ['type' => 'string', 'maxLength' => 250]],
            ],
        ];
    }

    /** @return array{recommendation: string, confidence: int, summary: string, opportunities: array, risks: array, key_factors: array} */
    private function decodeResult(string $text): array
    {
        $text = trim($text);

        try {
            $result = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('The Grid lieferte trotz Schema kein gültiges Ergebnis-JSON.', previous: $exception);
        }

        if (! is_array($result) || ! in_array($result['recommendation'] ?? null, self::RECOMMENDATIONS, true)) {
            throw new RuntimeException('The Grid lieferte keine gültige Einordnung.');
        }

        return [
            'recommendation' => $result['recommendation'],
            'confidence' => max(0, min(100, (int) ($result['confidence'] ?? 0))),
            'summary' => trim((string) ($result['summary'] ?? '')),
            'opportunities' => $this->stringList($result['opportunities'] ?? []),
            'risks' => $this->stringList($result['risks'] ?? []),
            'key_factors' => $this->stringList($result['key_factors'] ?? []),
        ];
    }

    /** @return array<int, string> */
    private function stringList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)->filter(fn ($item): bool => is_string($item))->map(fn (string $item): string => trim($item))->filter()->values()->all();
    }

    private function providerError(Response $response): string
    {
        $message = data_get($response->json(), 'error.message');

        return 'The Grid HTTP '.$response->status().': '.mb_substr(is_string($message) ? $message : $response->body(), 0, 1000);
    }
}
