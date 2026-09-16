<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SyncServingPredictions extends Command
{
    protected $signature = 'predictions:sync-serving {--since=1}';
    protected $description = 'Sync serving_predictions from serving DB to main DB';

    public function handle(): int
    {
        try {
            $since = (int)$this->option('since');
            $date = now()->subDays($since)->toDateString();

            $data = DB::connection('serving')
                ->table('serving_predictions')
                ->whereDate('created_at', '>=', $date)
                ->select('instrument_id', 'signal', 'expected_return', 'created_at')
                ->get();

            DB::connection('pgsql')->table('serving_predictions_copy')->truncate();

            foreach ($data->chunk(1000) as $chunk) {
                $rows = $chunk->map(fn ($row) => (array)$row)->all();
                DB::connection('pgsql')->table('serving_predictions_copy')->insertOrIgnore($rows);
            }

            $this->info('✓ Synced '.count($data).' serving predictions');
            return 0;
        } catch (\Exception $e) {
            $this->error('Sync failed: '.$e->getMessage());
            return 1;
        }
    }
}
