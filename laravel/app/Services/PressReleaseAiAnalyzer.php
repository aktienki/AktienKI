<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PressReleaseAiAnalyzer
{
    public function analyzePending(int $limit = 500): array
    {
        $apiKey = (string) env('OPENAI_API_KEY');
        if ($apiKey === '') throw new RuntimeException('OPENAI_API_KEY ist nicht konfiguriert.');

        $rows = DB::table('news as news')->join('instruments as instrument', 'instrument.id', '=', 'news.instrument_id')
            ->where('news.provider', 'twelve_data')->whereNull('news.ai_analyzed_at')
            ->orderBy('news.published_at')->limit(max(1, $limit))
            ->get(['news.id', 'news.headline', 'news.body', 'news.language', 'news.published_at', 'instrument.symbol', 'instrument.name']);
        $result = ['pending' => $rows->count(), 'analyzed' => 0, 'batches' => 0];

        foreach ($rows->chunk(max(1, (int) config('aktienki.news.openai_batch_size', 10))) as $batch) {
            $input = $batch->map(fn (object $row): array => [
                'id' => $row->id, 'symbol' => $row->symbol, 'company' => $row->name,
                'published_at' => $row->published_at, 'headline' => $row->headline,
                'text' => mb_substr((string) $row->body, 0, (int) config('aktienki.news.max_body_characters', 6000)),
            ])->values()->all();
            $response = Http::withToken($apiKey)->acceptJson()->asJson()->timeout(120)->post('https://api.openai.com/v1/responses', [
                'model' => (string) config('aktienki.news.openai_model', 'gpt-5.4-mini'),
                'instructions' => 'Du analysierst ausschließlich gelieferte offizielle Unternehmensmeldungen. Erfinde keine Fakten. Gib valides JSON ohne Markdown zurück. Sentiment liegt zwischen -1 und 1, Relevanz zwischen 0 und 100. Keine Anlageberatung.',
                'input' => 'Fasse jede Meldung sachlich in höchstens zwei kurzen Sätzen auf Deutsch und Englisch zusammen. Bewerte außerdem das unmittelbare unternehmensbezogene Sentiment und die Relevanz. Format: [{"id":1,"summary_de":"...","summary_en":"...","sentiment":0.0,"relevance":50}]. Meldungen: '.json_encode($input, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'max_output_tokens' => max(500, count($input) * 220),
                'metadata' => ['feature' => 'press-release-analysis'],
            ]);
            if ($response->failed()) throw new RuntimeException('OpenAI HTTP '.$response->status().': '.(string) data_get($response->json(), 'error.message', $response->body()));
            $payload = $response->json();
            $raw = trim((string) ($payload['output_text'] ?? data_get($payload, 'output.0.content.0.text', '')));
            $start = strpos($raw, '['); $end = strrpos($raw, ']');
            if ($start === false || $end === false) throw new RuntimeException('OpenAI lieferte kein JSON-Array.');
            $items = json_decode(substr($raw, $start, $end - $start + 1), true, flags: JSON_THROW_ON_ERROR);
            foreach ($items as $item) {
                if (! is_array($item) || ! in_array((int) ($item['id'] ?? 0), $batch->pluck('id')->map(fn ($id) => (int) $id)->all(), true)) continue;
                DB::table('news')->where('id', (int) $item['id'])->update([
                    'summary' => trim((string) ($item['summary_de'] ?? '')) ?: null,
                    'ai_summary_de' => trim((string) ($item['summary_de'] ?? '')) ?: null,
                    'ai_summary_en' => trim((string) ($item['summary_en'] ?? '')) ?: null,
                    'sentiment_score' => max(-1, min(1, (float) ($item['sentiment'] ?? 0))),
                    'relevance_score' => max(0, min(100, (int) ($item['relevance'] ?? 0))),
                    'ai_analyzed_at' => now(), 'updated_at' => now(),
                ]);
                $result['analyzed']++;
            }
            $result['batches']++;
        }

        return $result;
    }
}
