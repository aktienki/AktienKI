<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes the last 48h of press releases for actively tracked stocks into a
 * static JSON file the "News" dashboard section reads - same pattern as
 * storage/app/cache/today_highlights.json, kept as a transparent,
 * inspectable file rather than a cache entry.
 */
final class ExportRecentNewsJson extends Command
{
    protected $signature = 'news:export-recent-json {--hours=48}';

    protected $description = 'Exports recent press releases for active instruments to storage/app/cache/recent_news.json';

    public function handle(): int
    {
        $since = now()->subHours(max(1, (int) $this->option('hours')));

        $rows = DB::table('news as n')
            ->join('instruments as i', 'i.id', '=', 'n.instrument_id')
            ->where('i.is_active', true)
            ->whereNull('i.deleted_at')
            ->where('n.published_at', '>=', $since)
            ->orderByDesc('n.published_at')
            ->select([
                'n.headline', 'n.ai_summary_de', 'n.summary', 'n.source', 'n.url',
                'n.published_at', 'n.sentiment_score', 'n.relevance_score',
                'i.symbol', 'i.name', 'i.country', 'i.sector',
            ])
            ->get();

        $items = $rows->map(fn (object $row): array => [
            'symbol' => $row->symbol,
            'name' => $row->name,
            'country' => $row->country,
            'sector' => $row->sector,
            'headline' => $row->headline,
            'summary' => $row->ai_summary_de ?: $row->summary,
            'source' => $row->source,
            'url' => $row->url,
            'published_at' => $row->published_at,
            'sentiment' => is_numeric($row->sentiment_score) ? round((float) $row->sentiment_score, 2) : null,
            'relevance' => is_numeric($row->relevance_score) ? (int) $row->relevance_score : null,
        ])->values();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => (int) $this->option('hours'),
            'count' => $items->count(),
            'items' => $items,
        ];

        $filePath = storage_path('app/cache/recent_news.json');
        @mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("✓ {$items->count()} Meldungen der letzten {$this->option('hours')}h exportiert nach {$filePath}");

        return self::SUCCESS;
    }
}
