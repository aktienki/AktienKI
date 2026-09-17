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
        return $this->generateBatch([$stock])[$stock->symbol];
    }

    /**
     * Assesses several stocks in a single Grid request instead of one call
     * per stock: the system instructions (and their token cost) are paid
     * for once regardless of how many stocks are in the batch.
     *
     * @param  iterable<object>  $stocks
     * @return array<string, array{result: array, model: string, input_snapshot: array, raw_response: array}> keyed by symbol
     */
    public function generateBatch(iterable $stocks): array
    {
        $apiKey = trim((string) config('aktienki.stock_ai_assessment.grid_api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('GRID_API_KEY ist für die KI-Einordnung nicht konfiguriert.');
        }

        $inputs = [];
        foreach ($stocks as $stock) {
            $inputs[] = $this->stockContext($stock);
        }
        if ($inputs === []) {
            return [];
        }

        $model = (string) config('aktienki.stock_ai_assessment.grid_model', 'text-standard');
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => self::INSTRUCTIONS."\n\nDu bekommst eine Liste von Aktien. Antworte ausschließlich mit einem einzigen JSON-Objekt gemäß dem vorgegebenen Schema, das für JEDE Aktie aus der Liste einen Eintrag enthält (gleiche Reihenfolge, jeweils mit ihrem symbol) - kein Fließtext davor oder danach.",
                ],
                [
                    'role' => 'user',
                    'content' => json_encode(['stocks' => $inputs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['schema' => $this->batchResultSchema()],
            ],
            // Each stock needs roughly a single-assessment's worth of
            // reasoning + output budget; the shared system prompt is paid
            // for once, not per stock.
            'max_tokens' => min(16000, max(3500, count($inputs) * 1800)),
        ];

        $endpoint = (string) config('aktienki.stock_ai_assessment.grid_endpoint', 'https://api.thegrid.ai/v1/chat/completions');
        // The Grid's backend load-balances across providers; roughly one in
        // three requests using response_format/tools transiently 500s or
        // 400s on a provider that doesn't support the requested feature -
        // Http::retry() catches that. But a provider can also return 200
        // with malformed/incomplete content despite the schema (observed
        // directly), which isn't an HTTP-level failure, so it needs its
        // own retry around the decode step too.
        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->asJson()
                    ->connectTimeout(15)
                    ->timeout(180)
                    ->retry(3, 1500)
                    ->post($endpoint, $payload);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $lastError = new RuntimeException($this->providerError($e->response));

                continue;
            }

            $rawResponse = $response->json();
            if (! is_array($rawResponse)) {
                $lastError = new RuntimeException('The Grid lieferte keine gültige JSON-Antwort.');

                continue;
            }

            $text = (string) data_get($rawResponse, 'choices.0.message.content', '');

            try {
                $decoded = $this->decodeBatchResult($text);
            } catch (RuntimeException $e) {
                $lastError = $e;

                continue;
            }

            $lastError = null;
            break;
        }

        if ($lastError !== null) {
            throw $lastError;
        }

        $results = [];
        foreach ($inputs as $input) {
            $symbol = $input['symbol'];
            $entry = $decoded[$symbol] ?? null;
            if ($entry === null) {
                continue;
            }

            $results[$symbol] = [
                'result' => $entry,
                'model' => (string) ($rawResponse['model'] ?? $model),
                'input_snapshot' => $input,
                'raw_response' => $rawResponse,
            ];
        }

        return $results;
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
    private function assessmentSchema(bool $withSymbol): array
    {
        $properties = [
            'recommendation' => ['type' => 'string', 'enum' => self::RECOMMENDATIONS],
            'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'summary' => ['type' => 'string', 'maxLength' => 600],
            'opportunities' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 4, 'items' => ['type' => 'string', 'maxLength' => 250]],
            'risks' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 4, 'items' => ['type' => 'string', 'maxLength' => 250]],
            'key_factors' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 4, 'items' => ['type' => 'string', 'maxLength' => 250]],
        ];
        $required = ['recommendation', 'confidence', 'summary', 'opportunities', 'risks', 'key_factors'];

        if ($withSymbol) {
            $properties = ['symbol' => ['type' => 'string']] + $properties;
            array_unshift($required, 'symbol');
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $required,
            'properties' => $properties,
        ];
    }

    /** @return array<string, mixed> */
    private function batchResultSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['assessments'],
            'properties' => [
                'assessments' => [
                    'type' => 'array',
                    'items' => $this->assessmentSchema(withSymbol: true),
                ],
            ],
        ];
    }

    /** @return array<string, array{recommendation: string, confidence: int, summary: string, opportunities: array, risks: array, key_factors: array}> keyed by symbol */
    private function decodeBatchResult(string $text): array
    {
        // A request routed to a provider that doesn't fully honor
        // response_format can still wrap otherwise-valid JSON in a
        // markdown code fence, same as observed on plain-prompted calls.
        $text = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text)));

        try {
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('The Grid lieferte trotz Schema kein gültiges Ergebnis-JSON.', previous: $exception);
        }

        $assessments = $decoded['assessments'] ?? null;
        if (! is_array($assessments)) {
            throw new RuntimeException('The Grid lieferte keine gültige Liste von Einordnungen.');
        }

        $results = [];
        foreach ($assessments as $entry) {
            if (! is_array($entry) || ! is_string($entry['symbol'] ?? null)) {
                continue;
            }
            if (! in_array($entry['recommendation'] ?? null, self::RECOMMENDATIONS, true)) {
                continue;
            }

            $results[$entry['symbol']] = [
                'recommendation' => $entry['recommendation'],
                'confidence' => max(0, min(100, (int) ($entry['confidence'] ?? 0))),
                'summary' => trim((string) ($entry['summary'] ?? '')),
                'opportunities' => $this->stringList($entry['opportunities'] ?? []),
                'risks' => $this->stringList($entry['risks'] ?? []),
                'key_factors' => $this->stringList($entry['key_factors'] ?? []),
            ];
        }

        return $results;
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
