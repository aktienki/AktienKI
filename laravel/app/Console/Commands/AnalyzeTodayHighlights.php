<?php

namespace App\Console\Commands;

use App\Services\TodayHighlightsAnalysisService;
use App\Services\TodayHighlightsBuilder;
use Illuminate\Console\Command;

final class AnalyzeTodayHighlights extends Command
{
    protected $signature = 'highlights:analyze-today {--date=}';
    protected $description = 'Analyze trading highlights with The Grid and save to JSON';

    public function handle(): int
    {
        $dateStr = $this->option('date') ?? now()->toDateString();
        $this->info("Analyzing highlights for $dateStr...");

        try {
            $highlights = app(TodayHighlightsBuilder::class)->build($dateStr)['highlights'];

            foreach ($highlights as $h) {
                $this->line('  '.$h['label'].': '.($h['data'] ?? '(keine Daten)'));
            }

            $service = app(TodayHighlightsAnalysisService::class);
            $insights = $service->analyzeHighlights($highlights);

            $filePath = storage_path('app/cache/today_highlights.json');
            @mkdir(dirname($filePath), 0755, true);

            // Storing the exact highlight data these insights were written
            // for lets the dashboard detect a mismatch (e.g. a stale cache
            // from a manual re-run against a different date) and fall back
            // to live analysis instead of showing text that no longer
            // matches what's on screen.
            $cachePayload = [
                'data_snapshot' => array_map(fn (array $h) => $h['data'], $highlights),
                'insights' => $insights,
            ];

            file_put_contents($filePath, json_encode($cachePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info('✓ Highlights analyzed and saved to '.$filePath);
            return 0;
        } catch (\Exception $e) {
            $this->error('Analysis failed: '.$e->getMessage());
            return 1;
        }
    }
}
