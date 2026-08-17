<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

final class RunServerPredictions extends Command
{
    protected $signature = 'predictions:run-server
        {region : Handelsregion (other oder americas)}
        {--limit=5000 : Maximale Anzahl aktiver Modelle}
        {--no-refresh : Keine neuen Kursdaten abrufen}';

    protected $description = 'Erzeugt Tagesprognosen lokal auf dem Server mit der Python-Engine.';

    public function handle(): int
    {
        if (! config('aktienki.python_engine.server_predictions_enabled', false)) {
            $this->warn('Serverseitige Predictions sind deaktiviert.');

            return self::SUCCESS;
        }

        $region = (string) $this->argument('region');
        if (! in_array($region, ['other', 'americas'], true)) {
            $this->error('Region muss other oder americas sein.');

            return self::INVALID;
        }

        $path = (string) config('aktienki.python_engine.path');
        $executable = (string) (config('aktienki.python_engine.executable') ?: $path.'/.venv/bin/aktienki-engine');
        $command = [
            $executable,
            'predict-active',
            '--ai-type', 'horizon',
            '--market-region', $region,
            '--limit', (string) max(1, (int) $this->option('limit')),
        ];
        if ($this->option('no-refresh')) {
            $command[] = '--no-refresh';
        }

        $this->info("Starte serverseitige Predictions für {$region} …");
        $result = Process::path($path)
            ->timeout((int) config('aktienki.python_engine.prediction_timeout_seconds', 7200))
            ->run($command);

        Log::log($result->successful() ? 'info' : 'error', 'Server prediction batch finished.', [
            'region' => $region,
            'exit_code' => $result->exitCode(),
            'output' => trim($result->output()),
            'error_output' => trim($result->errorOutput()),
        ]);

        if (! $result->successful()) {
            $this->error(trim($result->errorOutput()) ?: 'Python-Predictionlauf fehlgeschlagen.');

            return self::FAILURE;
        }

        $this->line(trim($result->output()));

        return self::SUCCESS;
    }
}
