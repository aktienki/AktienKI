<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RefreshTodayHighlightsView extends Command
{
    protected $signature = 'highlights:refresh-view';
    protected $description = 'Refresh the materialized view for today\'s highlights';

    public function handle(): int
    {
        try {
            DB::connection('pgsql')->statement('REFRESH MATERIALIZED VIEW CONCURRENTLY today_highlights_mv');
            $this->info('✓ Materialized view refreshed');
            return 0;
        } catch (\Exception $e) {
            $this->error('Refresh failed: '.$e->getMessage());
            return 1;
        }
    }
}
