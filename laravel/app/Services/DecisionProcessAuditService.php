<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

final class DecisionProcessAuditService
{
    public const PIPELINE_VERSION = 'model-pipeline-v1.0';

    public function start(string $type, ?int $instrumentId, ?int $predictionId, array $context = []): int
    {
        $canonical = $this->canonicalJson($context);
        return (int) DB::table('decision_process_runs')->insertGetId([
            'public_id' => (string) Str::uuid(), 'instrument_id' => $instrumentId,
            'prediction_id' => $predictionId, 'process_type' => $type,
            'pipeline_version' => self::PIPELINE_VERSION, 'status' => 'RUNNING',
            'environment' => app()->environment(), 'context' => $canonical,
            'input_checksum' => hash('sha256', $canonical), 'started_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function step(int $runId, int $sequence, string $key, string $name, string $status, array $data = []): void
    {
        $startedAt = isset($data['started_at']) ? CarbonImmutable::parse($data['started_at']) : null;
        $finishedAt = isset($data['finished_at']) ? CarbonImmutable::parse($data['finished_at']) : now();
        $durationMs = isset($data['duration_ms'])
            ? (int) $data['duration_ms']
            : ($startedAt ? (int) round($startedAt->diffInMilliseconds($finishedAt)) : null);
        DB::table('decision_process_steps')->insert([
            'decision_process_run_id' => $runId, 'sequence' => $sequence,
            'stage_key' => $key, 'stage_name' => $name, 'status' => $status,
            'decision' => $data['decision'] ?? null, 'rule_version' => $data['rule_version'] ?? null,
            'raw_values' => $this->canonicalJson($data['raw_values'] ?? []),
            'normalized_values' => $this->canonicalJson($data['normalized_values'] ?? []),
            'thresholds' => $this->canonicalJson($data['thresholds'] ?? []),
            'sources' => $this->canonicalJson($data['sources'] ?? []),
            'evidence' => $this->canonicalJson($data['evidence'] ?? []),
            'reason' => $data['reason'] ?? null,
            'started_at' => $startedAt, 'finished_at' => $finishedAt,
            'duration_ms' => $durationMs, 'evaluated_at' => $finishedAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function finish(int $runId, string $decision, array $values = [], string $status = 'COMPLETED'): void
    {
        $finishedAt = now();
        $startedAt = DB::table('decision_process_runs')->where('id', $runId)->value('started_at');
        DB::table('decision_process_runs')->where('id', $runId)->update([
            'status' => $status, 'final_decision' => $decision,
            'final_values' => $this->canonicalJson($values), 'finished_at' => $finishedAt,
            'duration_ms' => $startedAt
                ? (int) round(CarbonImmutable::parse($startedAt)->diffInMilliseconds($finishedAt))
                : null,
            'updated_at' => $finishedAt,
        ]);
    }

    private function canonicalJson(array $value): string
    {
        $sort = function (&$item) use (&$sort): void {
            if (! is_array($item)) return;
            foreach ($item as &$child) $sort($child);
            if (! array_is_list($item)) ksort($item);
        };
        $sort($value);
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
