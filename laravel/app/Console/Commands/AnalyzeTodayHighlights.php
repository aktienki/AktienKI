<?php

namespace App\Console\Commands;

use App\Services\TodayHighlightsAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class AnalyzeTodayHighlights extends Command
{
    protected $signature = 'highlights:analyze-today';
    protected $description = 'Analyze today\'s trading highlights with The Grid and cache for 24h';

    public function handle(): int
    {
        $this->info('Analyzing today\'s highlights...');

        try {
            // Minimal highlights array (will be enriched by service with full data)
            $dummyHighlights = [
                ['data' => null],
                ['data' => null],
                ['data' => null],
                ['data' => null],
            ];

            $service = app(TodayHighlightsAnalysisService::class);
            $insights = $service->analyzeHighlights($dummyHighlights);

            // Cache for 24 hours
            Cache::put('today_highlights_insights', $insights, 86400);

            $this->info('✓ Highlights analyzed and cached for 24h');
            return 0;
        } catch (\Exception $e) {
            $this->error('Analysis failed: '.$e->getMessage());
            return 1;
        }
    }
}
