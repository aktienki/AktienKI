<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ServingDatabaseDoctor extends Command
{
    protected $signature = 'serving:doctor {--json : Ergebnis als JSON ausgeben}';

    protected $description = 'Prüft die getrennte, kompakte Serving-Datenbank ohne Daten zu verändern.';

    private const REQUIRED_TABLES = [
        'serving_active_models',
        'serving_instruments',
        'serving_instrument_fundamentals',
        'serving_prediction_batches',
        'serving_predictions',
        'serving_releases',
        'serving_schema_versions',
        'serving_signal_transitions',
        'serving_ui_snapshots',
        'serving_worker_jobs',
    ];

    private const FORBIDDEN_TABLES = [
        'backtest_strategy_trades',
        'backtest_trades',
        'feature_store',
        'price_bars',
        'technical_indicators',
        'trained_models',
        'walk_forward_backtest_trades',
        'walk_forward_horizon_forecasts',
    ];

    public function handle(): int
    {
        try {
            $connection = DB::connection('serving');
            $tables = $connection->table('information_schema.tables')
                ->where('table_schema', 'public')
                ->pluck('table_name')->map(static fn ($name): string => (string) $name)->all();
            $functions = $connection->table('pg_proc')->where('proname', 'prune_serving_history')->count();

            $missing = array_values(array_diff(self::REQUIRED_TABLES, $tables));
            $forbidden = array_values(array_intersect(self::FORBIDDEN_TABLES, $tables));
            $counts = [];
            foreach (['serving_instruments', 'serving_instrument_fundamentals', 'serving_releases', 'serving_predictions', 'serving_worker_jobs'] as $table) {
                $counts[$table] = in_array($table, $tables, true) ? $connection->table($table)->count() : null;
            }

            $result = [
                'ok' => $missing === [] && $forbidden === [] && $functions === 1,
                'database' => $connection->getDatabaseName(),
                'schema_version' => in_array('serving_schema_versions', $tables, true)
                    ? $connection->table('serving_schema_versions')->max('version') : null,
                'missing_tables' => $missing,
                'forbidden_tables' => $forbidden,
                'retention_function' => $functions === 1,
                'counts' => $counts,
            ];
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'error' => $exception->getMessage()];
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($result['ok']) {
            $this->info("Serving-Datenbank {$result['database']} ist strukturell bereit (Schema {$result['schema_version']}).");
            $this->table(['Bereich', 'Datensätze'], collect($result['counts'])->map(
                static fn ($count, string $table): array => [$table, $count]
            )->values()->all());
        } else {
            $this->error('Serving-Datenbank ist nicht bereit.');
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
