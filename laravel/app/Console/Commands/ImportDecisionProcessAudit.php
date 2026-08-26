<?php

namespace App\Console\Commands;

use App\Services\DecisionProcessAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ImportDecisionProcessAudit extends Command
{
    protected $signature = 'decision:audit-import {file : JSON audit file}';
    protected $description = 'Importiert einen reproduzierbaren Trainings-/Entscheidungs-Audit inklusive Laufzeiten.';

    public function handle(DecisionProcessAuditService $audit): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            throw new RuntimeException("Audit-Datei fehlt: {$file}");
        }
        $payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $run = $payload['run'] ?? [];
        $runId = $audit->start(
            (string) ($run['process_type'] ?? 'training'),
            isset($run['instrument_id']) ? (int) $run['instrument_id'] : null,
            isset($run['prediction_id']) ? (int) $run['prediction_id'] : null,
            (array) ($run['context'] ?? [])
        );
        foreach ((array) ($payload['steps'] ?? []) as $index => $step) {
            $audit->step(
                $runId,
                (int) ($step['sequence'] ?? (($index + 1) * 10)),
                (string) $step['stage_key'],
                (string) ($step['stage_name'] ?? $step['stage_key']),
                (string) ($step['status'] ?? 'PASSED'),
                (array) ($step['data'] ?? [])
            );
        }
        $final = $payload['final'] ?? [];
        $audit->finish(
            $runId,
            (string) ($final['decision'] ?? 'DOCUMENTED'),
            (array) ($final['values'] ?? []),
            (string) ($final['status'] ?? 'COMPLETED')
        );
        if (isset($run['duration_ms'])) {
            DB::table('decision_process_runs')->where('id', $runId)->update([
                'duration_ms' => (int) $run['duration_ms'],
            ]);
        }
        $this->info("Audit-Run {$runId} importiert.");
        return self::SUCCESS;
    }
}
