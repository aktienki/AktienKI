<?php

namespace App\Console\Commands;

use App\Services\EtfHoldingImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEtfHoldings extends Command
{
    protected $signature = 'etfs:sync-holdings {--fund-id=* : Nur bestimmte ETF-IDs synchronisieren}';
    protected $description = 'Importiert ETF-Bestände direkt aus den hinterlegten Anbieterdateien';

    public function handle(EtfHoldingImportService $importer): int
    {
        $query = DB::table('etf_funds')->where('is_active', true)->whereNotNull('source_url')->orderBy('id');
        if ($ids = array_filter($this->option('fund-id'))) $query->whereIn('id', $ids);
        $funds = $query->get();
        if ($funds->isEmpty()) {
            $this->warn('Keine aktiven ETF-Anbieterquellen hinterlegt.');
            return self::SUCCESS;
        }
        $failed = 0;
        foreach ($funds as $fund) {
            try {
                $result = $importer->sync($fund);
                $this->info("{$fund->name}: {$result['matched']}/{$result['imported']} Bestände dem Aktienuniversum zugeordnet.");
            } catch (\Throwable $exception) {
                $failed++;
                report($exception);
                $this->error("{$fund->name}: {$exception->getMessage()}");
            }
        }
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
