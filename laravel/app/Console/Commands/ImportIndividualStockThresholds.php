<?php

namespace App\Console\Commands;

use App\Models\Instrument;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

class ImportIndividualStockThresholds extends Command
{
    protected $signature = 'thresholds:import-individual
        {directory : Directory containing *_threshold.json reports}
        {--algorithm-version=phase-router-action-score-v1 : Immutable calculation version}';

    protected $description = 'Import versioned per-stock phase and AI-score threshold backtests';

    public function handle(): int
    {
        $directory = rtrim((string) $this->argument('directory'), DIRECTORY_SEPARATOR);
        $files = glob($directory.DIRECTORY_SEPARATOR.'*_threshold.json') ?: [];
        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            try {
                $raw = file_get_contents($file);
                $report = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->warn('Ungültiges JSON: '.$file);
                $skipped++;
                continue;
            }

            $symbol = (string) ($report['symbol'] ?? '');
            $instrument = Instrument::query()->where('symbol', $symbol)->first();
            $score = (array) ($report['individual_buy_threshold'] ?? []);
            $phase = (array) ($report['individual_phase_threshold'] ?? []);
            if (! $instrument || ! $score || ! $phase) {
                $this->warn("Übersprungen: {$symbol}");
                $skipped++;
                continue;
            }

            $phaseSelected = (array) ($phase['selected'] ?? []);
            $scoreSelected = (array) ($score['selected'] ?? []);
            $calculatedAt = filled($report['generated_at'] ?? null)
                ? CarbonImmutable::parse($report['generated_at']) : now();
            $now = now();

            DB::table('stock_individual_thresholds')->updateOrInsert([
                'instrument_id' => $instrument->id,
                'horizon_days' => (int) ($report['horizon'] ?? 15),
                'algorithm_version' => (string) $this->option('algorithm-version'),
            ], [
                'status' => (string) ($score['status'] ?? 'insufficient_data'),
                'minimum_phase_probability' => $phaseSelected['minimum_phase_probability'] ?? null,
                'minimum_ai_score' => $scoreSelected['minimum_ki_score'] ?? null,
                'event_count' => (int) ($score['event_count'] ?? 0),
                'calibration_event_count' => (int) ($score['calibration_event_count'] ?? 0),
                'validation_event_count' => (int) ($score['validation_event_count'] ?? 0),
                'validation_passed' => (bool) ($score['validation_passed'] ?? false),
                'validation_year' => $score['validation_year'] ?? null,
                'phase_result' => json_encode($phaseSelected ?: null, JSON_THROW_ON_ERROR),
                'score_result' => json_encode([
                    'selected' => $scoreSelected ?: null,
                    'validation' => $score['validation'] ?? null,
                ], JSON_THROW_ON_ERROR),
                'phase_matrix' => json_encode($phase['matrix'] ?? null, JSON_THROW_ON_ERROR),
                'score_matrix' => json_encode($score['matrix'] ?? null, JSON_THROW_ON_ERROR),
                'source_report_checksum' => hash('sha256', (string) $raw),
                'calculated_at' => $calculatedAt,
                'activated_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $imported++;
        }

        $this->info("Importiert: {$imported}; übersprungen: {$skipped}");

        return self::SUCCESS;
    }
}
