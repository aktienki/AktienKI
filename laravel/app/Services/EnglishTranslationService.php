<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Translates already-generated German AI text (stock_ai_assessments,
 * external_buy_reviews) into English - a plain, deterministic translation
 * task with no live web data needed, so The Grid's spot market is a cheap,
 * good fit (same reasoning as StockAiAssessmentService/instrument
 * descriptions).
 */
final class EnglishTranslationService
{
    /**
     * @param  string  $summary  German summary text
     * @param  array<string, list<string>>  $lists  named lists of German strings (e.g. ['opportunities' => [...]])
     * @return array{summary: string, lists: array<string, list<string>>}
     */
    public function translate(string $summary, array $lists): array
    {
        $apiKey = trim((string) config('aktienki.instrument_descriptions.grid_api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('GRID_API_KEY ist für Übersetzungen nicht konfiguriert.');
        }

        $input = ['summary' => $summary] + $lists;
        $model = (string) config('aktienki.instrument_descriptions.grid_model', 'text-prime');
        $endpoint = (string) config('aktienki.instrument_descriptions.grid_endpoint', 'https://api.thegrid.ai/v1/chat/completions');

        $properties = ['summary' => ['type' => 'string']];
        foreach (array_keys($lists) as $key) {
            $properties[$key] = ['type' => 'array', 'items' => ['type' => 'string']];
        }

        $response = Http::withToken($apiKey)->acceptJson()->asJson()->timeout(60)->post($endpoint, [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Du übersetzt bereits fertig formulierte deutsche Finanz-/Aktientexte ins Englische. '
                        .'Übersetze sinngemäß und natürlich, ohne neue Inhalte zu erfinden oder Aussagen zu verändern. '
                        .'Erhalte die Anzahl der Einträge in jeder Liste exakt (gleiche Reihenfolge, ein Eintrag pro Übersetzung). '
                        .'Antworte ausschließlich mit einem einzigen JSON-Objekt gemäß dem vorgegebenen Schema - kein Fließtext davor oder danach.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => array_keys($properties),
                        'properties' => $properties,
                    ],
                ],
            ],
            'max_tokens' => 1200,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('HTTP '.$response->status().': '.(string) data_get($response->json(), 'error.message', 'The Grid liefert einen Fehler.'));
        }

        $raw = (string) data_get($response->json(), 'choices.0.message.content', '');
        $result = json_decode(trim($raw), true);
        if (! is_array($result) || ! isset($result['summary'])) {
            throw new RuntimeException('The Grid lieferte keine gültige Übersetzung.');
        }

        $translatedLists = [];
        foreach (array_keys($lists) as $key) {
            $translatedLists[$key] = array_values(array_map('strval', (array) ($result[$key] ?? [])));
        }

        return ['summary' => trim((string) $result['summary']), 'lists' => $translatedLists];
    }
}
