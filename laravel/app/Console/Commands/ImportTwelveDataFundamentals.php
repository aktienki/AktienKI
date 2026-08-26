<?php

namespace App\Console\Commands;

use App\Services\TwelveDataFundamentalImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportTwelveDataFundamentals extends Command
{
    protected $signature = 'fundamentals:import-twelve-data {--limit=0} {--force} {--missing-only : Import only stocks without any fundamental snapshot} {--analysis-only} {--sleep=61000}';
    protected $description = 'One-time import of Twelve Data fundamentals for active stocks';

    public function handle(TwelveDataFundamentalImporter $importer): int
    {
        $query = DB::table('instruments')->where('type', 'stock')->where('is_active', true)->whereNull('deleted_at')->orderBy('id');
        if ($this->option('missing-only') && ! $this->option('analysis-only')) {
            $query->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('instrument_fundamentals as f')
                ->whereColumn('f.instrument_id', 'instruments.id'));
        } elseif (! $this->option('force')) {
            $table = $this->option('analysis-only') ? 'instrument_analyst_consensuses' : 'instrument_fundamentals';
            $alias = $this->option('analysis-only') ? 'a' : 'f';
            $query->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from($table.' as '.$alias)
                ->whereColumn($alias.'.instrument_id', 'instruments.id')->where($alias.'.source', 'twelve_data')->whereDate($alias.'.snapshot_date', today()));
        }
        $limit = max(0, (int) $this->option('limit'));
        if ($limit) $query->limit($limit);
        $stocks = $query->get(['id', 'symbol', 'provider_symbol']);
        $bar = $this->output->createProgressBar($stocks->count());
        $bar->start();
        $success = $failed = $skipped = 0;

        foreach ($stocks as $stock) {
            $imported = false;
            for ($attempt = 1; $attempt <= 3 && ! $imported; $attempt++) {
                try {
                    $this->option('analysis-only') ? $importer->importAnalysis($stock) : $importer->import($stock);
                    $success++;
                    $imported = true;
                } catch (Throwable $exception) {
                    $message = strtolower($exception->getMessage());
                    $planRestricted = str_contains($message, 'exclusively with ultra or enterprise');
                    if ($this->option('analysis-only') && $planRestricted) {
                        $skipped++;
                        $imported = true;
                        if ($skipped === 1) {
                            $this->newLine();
                            $this->warn('Analysis-Endpunkte sind für dieses Symbol im aktuellen TwelveData-Tarif gesperrt; weitere Sperren werden still übersprungen.');
                        }
                        continue;
                    }
                    $rateLimited = str_contains($message, 'api credits');
                    if ($rateLimited && $attempt < 3) {
                        $this->newLine();
                        $this->warn($stock->symbol.': Minutenlimit erreicht, Wiederholung nach 65 Sekunden.');
                        sleep(65);
                        continue;
                    }
                    $failed++;
                    $this->newLine();
                    $this->warn($stock->symbol.': '.$exception->getMessage());
                }
            }
            $bar->advance();
            usleep(max(0, (int) $this->option('sleep')) * 1000);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Import abgeschlossen: {$success} erfolgreich, {$skipped} tarifbedingt übersprungen, {$failed} fehlgeschlagen.");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
