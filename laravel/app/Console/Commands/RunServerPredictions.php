<?php

namespace App\Console\Commands;

use App\Notifications\PredictionBatchCompletedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;

final class RunServerPredictions extends Command
{
    protected $signature = 'predictions:run-server
        {region : Handelsregion (all, asia, europe, other oder americas)}
        {--limit=5000 : Maximale Anzahl Aktien; intern werden vier Horizonmodelle je Aktie berücksichtigt}
        {--symbols=* : Optional nur diese Symbole berechnen}
        {--recalculate : Bereits vorhandene Predictions derselben Quellkerze neu berechnen}
        {--no-refresh : Keine neuen Kursdaten abrufen}
        {--defer-finalization : Regionale Kern- und Phasenprognosen schreiben, aber den Tagessnapshot noch nicht veröffentlichen}
        {--finalize-only : Keine Kernprognosen starten; Filter, Fusion und Tagesausgabe finalisieren}
        {--minimum-coverage=0.95 : Mindestanteil vollständiger 5T-20T-Snapshots vor der Finalisierung}
        {--completion-email= : Abschlussbericht erst nach vollständig durchlaufenem Gesamtbatch senden}
        {--send-digest : Pro-Tagesmail nach erfolgreichem Abschluss versenden}';

    protected $description = 'Erzeugt Tagesprognosen lokal auf dem Server mit der Python-Engine.';

    public function handle(): int
    {
        if (! config('aktienki.python_engine.server_predictions_enabled', false)) {
            $this->warn('Serverseitige Predictions sind deaktiviert.');

            return self::SUCCESS;
        }

        $region = (string) $this->argument('region');
        if (! in_array($region, ['all', 'asia', 'europe', 'other', 'americas'], true)) {
            $this->error('Region muss all, asia, europe, other oder americas sein.');

            return self::INVALID;
        }

        // Training workers intentionally leave unfinished stocks inactive.
        // Promote only stocks whose four active models and four completed
        // walk-forward horizons are present before predict-active selects them.
        if ($this->call('stocks:activate-completed-training') !== self::SUCCESS) {
            $this->error('Vollständig trainierte Aktien konnten nicht freigegeben werden.');
            return self::FAILURE;
        }

        $path = (string) config('aktienki.python_engine.path');
        $executable = (string) (config('aktienki.python_engine.executable') ?: $path.'/.venv/bin/aktienki-engine');
        $stockLimit = max(1, (int) $this->option('limit'));
        $symbols = collect($this->option('symbols'))
            ->map(fn ($symbol): string => strtoupper(trim((string) $symbol)))
            ->filter()->unique()->values();
        if ($symbols->isEmpty() && (
            in_array($region, ['asia', 'europe'], true)
            || ($this->option('defer-finalization') && $region === 'americas')
        )) {
            $symbols = $this->regionalSymbols($region, $stockLimit);
        }

        if ($this->option('finalize-only')) {
            return $this->finalizeSnapshot($path, $executable);
        }

        $command = [
            $executable,
            'predict-active',
            '--ai-type', 'horizon',
            // predict-active limits model scopes, not instruments.
            '--limit', (string) ($stockLimit * 4),
        ];
        if (in_array($region, ['other', 'americas'], true) && $symbols->isEmpty()) {
            array_splice($command, 4, 0, ['--market-region', $region]);
        }
        if ($this->option('no-refresh')) {
            $command[] = '--no-refresh';
        }
        if ($this->option('recalculate')) {
            $command[] = '--recalculate';
        }
        if ($symbols->isNotEmpty()) {
            $command[] = '--symbols';
            array_push($command, ...$symbols->all());
        }

        $scope = $symbols->isNotEmpty()
            ? sprintf('%s (%d Aktien)', $region, $symbols->count())
            : $region;
        $this->info("Starte serverseitige Predictions für {$scope} …");
        $result = Process::path($path)
            ->timeout((int) config('aktienki.python_engine.prediction_timeout_seconds', 7200))
            ->run($command);

        Log::log($result->successful() ? 'info' : 'error', 'Server prediction batch finished.', [
            'region' => $region,
            'exit_code' => $result->exitCode(),
            'output' => trim($result->output()),
            'error_output' => trim($result->errorOutput()),
        ]);

        $completed = preg_match('/\bcompleted=(\d+)/', $result->output(), $matches) === 1
            ? (int) $matches[1]
            : 0;

        if (! $result->successful() && $completed === 0) {
            $this->error(
                trim($result->errorOutput())
                ?: trim($result->output())
                ?: 'Python-Predictionlauf fehlgeschlagen.'
            );

            return self::FAILURE;
        }

        if (! $result->successful()) {
            $this->warn("Predictionlauf mit Teilerfolg: {$completed} Modellscopes abgeschlossen; fehlende Artefakte wurden übersprungen.");
        }

        $this->line(trim($result->output()));
        $this->info('Predictions abgeschlossen. Aktualisiere die PyTorch-Phasenfilter …');

        $phaseCommand = [$path.'/.venv/bin/python', 'scripts/predict_stock_phase_filters.py'];
        if ($symbols->isNotEmpty()) {
            $phaseCommand[] = '--symbols';
            array_push($phaseCommand, ...$symbols->all());
        }
        $phaseResult = Process::path($path)
            ->timeout((int) config('aktienki.python_engine.prediction_timeout_seconds', 7200))
            ->run($phaseCommand);
        Log::log($phaseResult->successful() ? 'info' : 'warning', 'Stock phase-filter batch finished.', [
            'exit_code' => $phaseResult->exitCode(),
            'output' => trim($phaseResult->output()),
            'error_output' => trim($phaseResult->errorOutput()),
        ]);
        if (! $phaseResult->successful()) {
            $this->warn('Phasenfilter konnten nicht aktualisiert werden; vorhandene Filterwerte bleiben erhalten.');
        } else {
            $this->line(trim($phaseResult->output()));
        }

        if ($this->option('defer-finalization') || $symbols->isNotEmpty()) {
            $this->info('Regionaler Batch abgeschlossen; die Veröffentlichung wartet auf den finalen Tageslauf.');
            return self::SUCCESS;
        }

        $finalizationStatus = $this->finalizeSnapshot($path, $executable);
        if ($email = trim((string) $this->option('completion-email'))) {
            $this->sendCompletionEmail($email, $region, $result, $phaseResult, $finalizationStatus);
        }

        return $finalizationStatus;
    }

    private function sendCompletionEmail(string $email, string $region, $predictionResult, $phaseResult, int $finalizationStatus): void
    {
        $output = trim($predictionResult->output());
        preg_match('/\bcompleted=(\d+)/', $output, $completedMatch);
        preg_match('/\bskipped=(\d+)/', $output, $skippedMatch);
        preg_match('/\bfailed=(\d+)/', $output, $failedMatch);
        $completed = (int) ($completedMatch[1] ?? 0);
        $skipped = (int) ($skippedMatch[1] ?? 0);
        $failed = (int) ($failedMatch[1] ?? 0);
        preg_match_all('/^-\s+(.+)$/m', $output."\n".trim($predictionResult->errorOutput()), $errorMatches);
        $errors = collect($errorMatches[1] ?? [])->map(fn ($error) => (string) $error)->take(30);
        if (! $phaseResult->successful()) {
            $errors->push('Phasenfilter: '.(trim($phaseResult->errorOutput()) ?: 'fehlgeschlagen'));
        }
        if ($finalizationStatus !== self::SUCCESS) {
            $errors->push('Finalisierung oder Mindestabdeckung fehlgeschlagen.');
        }
        $statusOk = $failed === 0 && $predictionResult->successful() && $phaseResult->successful() && $finalizationStatus === self::SUCCESS;
        Notification::route('mail', $email)->notifyNow(new PredictionBatchCompletedNotification([
            'statusOk' => $statusOk,
            'region' => $region,
            'finishedAt' => now()->timezone('Europe/Berlin')->format('d.m.Y H:i').' Uhr',
            'completed' => $completed,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors->values()->all(),
        ]));
        $this->info("Prediction-Abschlussbericht an {$email} versendet.");
    }

    private function finalizeSnapshot(string $path, string $executable): int
    {
        $coverage = $this->predictionCoverage();
        $minimumCoverage = max(0.0, min(1.0, (float) $this->option('minimum-coverage')));
        $this->line(sprintf(
            'Snapshot-Abdeckung: %d/%d Aktien (%.1f%%).',
            $coverage['complete'], $coverage['eligible'], $coverage['ratio'] * 100,
        ));
        if ($coverage['eligible'] > 0 && $coverage['ratio'] < $minimumCoverage) {
            $this->error(sprintf(
                'Tagessnapshot nicht veröffentlicht: Mindestabdeckung %.1f%% unterschritten.',
                $minimumCoverage * 100,
            ));
            return self::FAILURE;
        }

        // Technical indicators consume the same completed daily price snapshot.
        if ($this->call('chartview:refresh-signals') !== self::SUCCESS) {
            $this->error('Indikatorfilter konnten nicht aktualisiert werden.');
            return self::FAILURE;
        }

        // Sector and index models are context-only. Their daily values must be
        // present before the four production horizons are fused.
        $sectorArtifact = (string) config('aktienki.python_engine.sector_filter_artifact');
        $sectorReport = (string) config('aktienki.python_engine.sector_filter_report');
        if ($sectorArtifact !== '' && $sectorReport !== '') {
            $sectorResult = Process::path($path)
                ->timeout((int) config('aktienki.python_engine.prediction_timeout_seconds', 7200))
                ->run([
                    $path.'/.venv/bin/python', 'scripts/predict_sector_pytorch_60t.py',
                    '--artifact', $sectorArtifact, '--report', $sectorReport,
                ]);
            if (! $sectorResult->successful()) {
                $this->error(trim($sectorResult->errorOutput()) ?: 'Sektorfilter konnten nicht aktualisiert werden.');
                return self::FAILURE;
            }
            $this->line(trim($sectorResult->output()));
            if ($this->call('predictions:index-pytorch60-context', ['--max-members' => 25]) !== self::SUCCESS) {
                $this->error('Indexfilter konnten nicht aktualisiert werden.');
                return self::FAILURE;
            }
        } else {
            $this->warn('Kein portables Sektorfilter-Artefakt konfiguriert; Sektor-/Indexfilter bleiben unverändert.');
        }

        $this->info('Aktualisiere KI-Scores und Aktienklassifizierung …');

        foreach ([
            ['predictions:apply-horizon-fusion', []],
            ['scores:recalculate', []],
            ['stocks:classify-risk', []],
        ] as [$postCommand, $arguments]) {
            if ($this->call($postCommand, $arguments) !== self::SUCCESS) {
                $this->error("Nachgelagerter Schritt {$postCommand} ist fehlgeschlagen.");

                return self::FAILURE;
            }
        }

        if ($this->option('send-digest')) {
            $this->info('Predictions abgeschlossen. Erstelle die aktuelle ChatGPT-Markteinschätzung …');

            $marketAnalysis = Process::path($path)
                ->timeout(900)
                ->run([$executable, 'analyze-market-ai']);
            Log::log($marketAnalysis->successful() ? 'info' : 'error', 'Daily ChatGPT market analysis finished.', [
                'exit_code' => $marketAnalysis->exitCode(),
                'output' => trim($marketAnalysis->output()),
                'error_output' => trim($marketAnalysis->errorOutput()),
            ]);
            if (! $marketAnalysis->successful()) {
                $this->error(trim($marketAnalysis->errorOutput()) ?: 'ChatGPT-Markteinschätzung fehlgeschlagen.');

                return self::FAILURE;
            }

            $this->line(trim($marketAnalysis->output()));
            $this->info('Markteinschätzung abgeschlossen. Aktualisiere die Nutzerinformationen …');

            foreach ([
                ['predictions:market-context', []],
                ['chartview:refresh-signals', []],
                ['markets:generate-index-infos', []],
                ['stocks:screen-top100', ['--limit' => 10, '--force' => true, '--with-ai' => true]],
                ['signals:send-entry-alerts', []],
                ['predictions:send-purchase-reminders', []],
                ['opportunities:sync', []],
            ] as [$command, $arguments]) {
                if ($this->call($command, $arguments) !== self::SUCCESS) {
                    $this->error("Nachgelagerter Schritt {$command} ist fehlgeschlagen; der Digest wird nicht versendet.");

                    return self::FAILURE;
                }
            }

            $this->info('Nutzerinformationen aktualisiert. Versende jetzt den Pro-Tagesbericht …');

            return $this->call('dashboard:send-digest', ['--all' => true]);
        }

        return self::SUCCESS;
    }

    private function regionalSymbols(string $region, int $limit)
    {
        $asia = ['AU', 'CN', 'HK', 'ID', 'IN', 'JP', 'KR', 'MY', 'NZ', 'PH', 'PK', 'SG', 'TH', 'TW', 'VN'];
        $americas = ['AR', 'BO', 'BR', 'CA', 'CL', 'CO', 'EC', 'GY', 'MX', 'PE', 'PY', 'SR', 'US', 'UY', 'VE'];
        $query = DB::table('instruments')->whereNull('deleted_at')->where('is_active', true)
            ->whereRaw('LOWER(type) = ?', ['stock']);
        if ($region === 'asia') {
            $query->whereIn(DB::raw('UPPER(country)'), $asia);
        } elseif ($region === 'americas') {
            $query->whereIn(DB::raw('UPPER(country)'), $americas);
        } else {
            $query->whereNotIn(DB::raw('UPPER(country)'), $asia)
                ->whereNotIn(DB::raw('UPPER(country)'), $americas);
        }
        return $query->orderByDesc('market_cap')->limit($limit)->pluck('symbol')
            ->map(fn ($symbol): string => strtoupper((string) $symbol))->values();
    }

    private function predictionCoverage(): array
    {
        $row = DB::selectOne(<<<'SQL'
            WITH eligible AS (
                SELECT instrument.id
                FROM instruments instrument
                WHERE instrument.deleted_at IS NULL AND instrument.is_active=TRUE
                  AND LOWER(instrument.type)='stock'
                  AND (SELECT COUNT(DISTINCT model.prediction_horizon_minutes)
                       FROM trained_models model
                       WHERE model.instrument_id=instrument.id AND model.deleted_at IS NULL
                         AND model.status='active' AND model.ai_type='horizon'
                         AND model.feature_set_version='triple_daily_macro_v1'
                         AND model.prediction_horizon_minutes IN (7200,14400,21600,28800))=4
            ), latest_bar AS (
                SELECT bar.instrument_id, MAX(bar.bar_time) AS bar_time
                FROM price_bars bar JOIN eligible ON eligible.id=bar.instrument_id
                WHERE bar.interval='1d' GROUP BY bar.instrument_id
            ), complete AS (
                SELECT prediction.instrument_id
                FROM predictions prediction
                JOIN latest_bar ON latest_bar.instrument_id=prediction.instrument_id
                               AND latest_bar.bar_time=prediction.source_bar_time
                WHERE prediction.ai_type='horizon' AND prediction.timeframe='1d'
                  AND prediction.prediction_horizon_minutes IN (7200,14400,21600,28800)
                GROUP BY prediction.instrument_id
                HAVING COUNT(DISTINCT prediction.prediction_horizon_minutes)=4
            )
            SELECT (SELECT COUNT(*) FROM eligible) AS eligible,
                   (SELECT COUNT(*) FROM complete) AS complete
            SQL);
        $eligible = (int) ($row->eligible ?? 0);
        $complete = (int) ($row->complete ?? 0);
        return ['eligible' => $eligible, 'complete' => $complete,
            'ratio' => $eligible > 0 ? $complete / $eligible : 1.0];
    }
}
