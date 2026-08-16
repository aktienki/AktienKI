<?php

namespace App\Console\Commands;

use App\Jobs\RunFilteredBacktest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RunFilteredBacktestLocally extends Command
{
    protected $signature = 'backtest:run-local {run : ID oder Public-ID des Backtest-Laufs}';
    protected $description = 'Führt einen gefilterten Backtest synchron lokal und ohne Queue-Timeout aus.';

    public function handle(): int
    {
        $value = (string) $this->argument('run');
        $run = DB::table('backtest_runs')->where(is_numeric($value) ? 'id' : 'public_id', $value)->first();
        if (! $run) {
            $this->error('Backtest-Lauf nicht gefunden.');
            return self::FAILURE;
        }
        $settings = is_string($run->settings) ? (json_decode($run->settings, true) ?: []) : (array) $run->settings;
        $sourceRunId = (int) data_get($settings, 'source_run_id', 0);
        $filters = (array) data_get($settings, 'selection_filters', []);
        if ($sourceRunId < 1) {
            $this->error('Der Ausgangslauf fehlt.');
            return self::FAILURE;
        }
        $this->info("Lokaler Backtest {$run->id} gestartet …");
        RunFilteredBacktest::dispatchSync((int) $run->id, $sourceRunId, $filters);
        $finished = DB::table('backtest_runs')->where('id', $run->id)->first(['status', 'trades_count']);
        $this->info("Status: {$finished->status}; Trades: {$finished->trades_count}");
        return $finished->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
