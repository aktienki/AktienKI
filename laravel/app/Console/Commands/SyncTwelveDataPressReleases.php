<?php

namespace App\Console\Commands;

use App\Services\PressReleaseAiAnalyzer;
use App\Services\TwelveDataPressReleaseImporter;
use Illuminate\Console\Command;
use Throwable;

final class SyncTwelveDataPressReleases extends Command
{
    protected $signature = 'news:sync-press-releases {--limit=2500} {--analyze} {--analysis-limit=500}';
    protected $description = 'Synchronisiert neue Twelve-Data-Pressemitteilungen des Aktienuniversums und analysiert nur neue Datensätze';

    public function handle(TwelveDataPressReleaseImporter $importer, PressReleaseAiAnalyzer $analyzer): int
    {
        try {
            $result = $importer->sync(max(1, (int) $this->option('limit')));
            $this->info("Pressemitteilungen: {$result['checked']} Aktien geprüft, {$result['created']} neue Meldungen, {$result['failed']} Fehler.");
            if ($this->option('analyze')) {
                $ai = $analyzer->analyzePending(max(1, (int) $this->option('analysis-limit')));
                $this->info("GPT: {$ai['analyzed']} neue Meldungen in {$ai['batches']} Paketen analysiert.");
            }
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
