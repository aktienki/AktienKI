<?php

namespace App\Console\Commands;

use App\Services\TodayHighlightsAnalysisService;
use Illuminate\Console\Command;

final class AnalyzeTodayHighlights extends Command
{
    protected $signature = 'highlights:analyze-today';
    protected $description = 'Analyze today\'s trading highlights with The Grid and save to JSON';

    public function handle(): int
    {
        $this->info('Analyzing today\'s highlights...');

        try {
            $dummyHighlights = [
                ['data' => null],
                ['data' => null],
                ['data' => null],
                ['data' => null],
            ];

            $service = app(TodayHighlightsAnalysisService::class);
            $insights = $service->analyzeHighlights($dummyHighlights);

            $filePath = storage_path('app/cache/today_highlights.json');
            @mkdir(dirname($filePath), 0755, true);

            file_put_contents($filePath, json_encode($insights, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info('✓ Highlights analyzed and saved to '.$filePath);
            return 0;
        } catch (\Exception $e) {
            $this->error('Analysis failed: '.$e->getMessage());
            return 1;
        }
    }
}
