<?php

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;

/** Builds deterministic, append-stable serving-session evidence. */
final class FinalEntrySessionEvidenceBuilder
{
    /** @var list<string> */
    private const SIGNALS = ['BUY', 'HOLD', 'WATCH', 'WAIT', 'SELL'];

    public function __construct(
        private readonly FinalEntryCanonicalizer $canonicalizer,
    ) {}

    /**
     * @param  list<array<string,mixed>>  $rows  Exact scopes from the one next completed batch.
     * @param  array<string,mixed>  $mapping
     * @param  array<string,mixed>|null  $priorSnapshot  Previously verified immutable prefix.
     * @return array<string,mixed>
     */
    public function build(
        array $rows,
        array $mapping,
        DateTimeImmutable $coverageStart,
        DateTimeImmutable $verifiedThrough,
        DateTimeImmutable $heartbeatAt,
        ?array $priorSnapshot,
        bool $coverageComplete,
    ): array {
        $localInstrumentId = $this->positiveInt($mapping, 'local_instrument_id');
        $sourceInstrumentId = $this->positiveInt($mapping, 'serving_instrument_id');
        $mappingHash = strtolower(trim((string) ($mapping['sha256'] ?? '')));
        $timezoneName = trim((string) ($mapping['session_timezone'] ?? ''));
        if (preg_match('/^[0-9a-f]{64}$/', $mappingHash) !== 1
            || $timezoneName === '') {
            throw new LogicException('SESSION_MAPPING_INVALID');
        }
        try {
            $timezone = new DateTimeZone($timezoneName);
        } catch (\Throwable) {
            throw new LogicException('SESSION_TIMEZONE_INVALID');
        }
        if (! $coverageComplete) {
            throw new LogicException('SESSION_EVIDENCE_COVERAGE_UNPROVEN');
        }
        if ($verifiedThrough < $coverageStart || $heartbeatAt < $verifiedThrough) {
            throw new LogicException('SESSION_EVIDENCE_WATERMARK_INVALID');
        }

        /** @var array<string,array<string,mixed>> $batches */
        $batches = [];
        $seenTuples = [];
        $scopeManifestHash = null;
        $outboxSequence = null;
        $outboxPayloadHash = null;
        foreach ($rows as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new LogicException('SESSION_SOURCE_ROW_INVALID');
            }
            $batchId = $this->requiredString($row, 'batch_id');
            $releaseId = $this->requiredString($row, 'release_id');
            $pipelineVersion = $this->requiredString($row, 'pipeline_version');
            $calculationDate = $this->date($row, 'calculation_date');
            $asOf = $this->timestamp($row, 'as_of');
            $finishedAt = $this->timestamp($row, 'batch_finished_at');
            $instrumentId = $this->positiveInt($row, 'instrument_id');
            $predictionId = $this->positiveInt($row, 'prediction_id');
            $horizon = $this->positiveInt($row, 'horizon');
            $variant = $this->requiredString($row, 'variant');
            $releaseContentHash = strtolower(trim((string) (
                $row['release_content_sha256'] ?? ''
            )));
            $rowScopeManifestHash = strtolower(trim((string) (
                $row['scope_manifest_sha256'] ?? ''
            )));
            $rowOutboxSequence = $this->positiveInt($row, 'outbox_sequence_no');
            $rowOutboxPayloadHash = strtolower(trim((string) (
                $row['outbox_payload_sha256'] ?? ''
            )));
            $signal = strtoupper(trim((string) ($row['signal'] ?? '')));
            // Selection is frozen evidence used for ranking, not a gate.
            $this->databaseBool($row['selected_for_prediction'] ?? null);
            if (($row['batch_status'] ?? null) !== 'complete'
                || $instrumentId !== $sourceInstrumentId
                || ! $this->databaseBool($row['prediction_enabled'] ?? null)
                || ($row['prediction_status'] ?? null) !== 'eligible'
                || ! $this->databaseBool($row['source_release_bound'] ?? null)
                || preg_match('/^[0-9a-f]{64}$/', $releaseContentHash) !== 1
                || preg_match('/^[0-9a-f]{64}$/', $rowScopeManifestHash) !== 1
                || preg_match('/^[0-9a-f]{64}$/', $rowOutboxPayloadHash) !== 1
                || ! in_array($signal, self::SIGNALS, true)
                || $asOf < $coverageStart
                || $asOf > $verifiedThrough
                || $finishedAt < $asOf
                || $finishedAt > $heartbeatAt->add(new DateInterval('PT5M'))) {
                throw new LogicException('SESSION_SOURCE_ROW_INVALID');
            }
            if ($scopeManifestHash !== null
                && ! hash_equals($scopeManifestHash, $rowScopeManifestHash)) {
                throw new LogicException('SESSION_SOURCE_SCOPE_MANIFEST_INCONSISTENT');
            }
            $scopeManifestHash = $rowScopeManifestHash;
            if (($outboxSequence !== null && $outboxSequence !== $rowOutboxSequence)
                || ($outboxPayloadHash !== null
                    && ! hash_equals($outboxPayloadHash, $rowOutboxPayloadHash))) {
                throw new LogicException('SESSION_SOURCE_OUTBOX_INCONSISTENT');
            }
            $outboxSequence = $rowOutboxSequence;
            $outboxPayloadHash = $rowOutboxPayloadHash;
            $tuple = implode(chr(0), [$batchId, $releaseId, (string) $horizon, $variant]);
            if (isset($seenTuples[$tuple])) {
                throw new LogicException('SESSION_SOURCE_SCOPE_DUPLICATE');
            }
            $seenTuples[$tuple] = true;

            $asOfText = $this->canonicalizer->databaseTimestamp($asOf);
            $finishedText = $this->canonicalizer->databaseTimestamp($finishedAt);
            if (! isset($batches[$batchId])) {
                $batches[$batchId] = [
                    'batch_id' => $batchId,
                    'release_id' => $releaseId,
                    'as_of' => $asOfText,
                    'batch_finished_at' => $finishedText,
                    'calculation_date' => $calculationDate,
                    'pipeline_version' => $pipelineVersion,
                    'predictions' => [],
                ];
            } elseif ($batches[$batchId]['release_id'] !== $releaseId
                || $batches[$batchId]['as_of'] !== $asOfText
                || $batches[$batchId]['batch_finished_at'] !== $finishedText
                || $batches[$batchId]['calculation_date'] !== $calculationDate
                || $batches[$batchId]['pipeline_version'] !== $pipelineVersion) {
                throw new LogicException('SESSION_SOURCE_BATCH_INCONSISTENT');
            }

            $batches[$batchId]['predictions'][] = [
                'prediction_id' => $predictionId,
                'release_id' => $releaseId,
                'release_content_sha256' => $releaseContentHash,
                'horizon' => $horizon,
                'variant' => $variant,
                'signal' => $signal,
                'expected_return' => $row['expected_return'] ?? null,
                'target_price' => $row['target_price'] ?? null,
                'calibrated_score' => $row['calibrated_score'] ?? null,
                'risk_score' => $row['risk_score'] ?? null,
                'confidence' => $row['confidence'] ?? null,
                'compact_context' => $this->jsonValue($row['compact_context'] ?? null),
            ];
        }
        if ($batches === []) {
            throw new LogicException('SESSION_SOURCE_EVIDENCE_MISSING');
        }
        if (count($batches) !== 1) {
            throw new LogicException('SESSION_SOURCE_BATCH_NOT_EXACTLY_ONE');
        }

        $batch = array_values($batches)[0];
        usort(
            $batch['predictions'],
            static fn (array $left, array $right): int => [
                $left['horizon'], $left['variant'], $left['prediction_id'],
            ] <=> [
                $right['horizon'], $right['variant'], $right['prediction_id'],
            ],
        );
        $asOf = $this->canonicalizer->dateTime($batch['as_of']);
        if (! $this->sameInstant($asOf, $verifiedThrough)) {
            throw new LogicException('SESSION_EVIDENCE_WATERMARK_NOT_BATCH_BOUND');
        }
        $marketDate = $asOf->setTimezone($timezone)->format('Y-m-d');
        $eventIdentity = [
            'schema' => 'serving-prediction-session-v1',
            'provider_name' => 'serving_prediction',
            'serving_instrument_id' => $sourceInstrumentId,
            'batch_id' => $batch['batch_id'],
            'outbox_sequence_no' => $outboxSequence,
            'outbox_payload_sha256' => $outboxPayloadHash,
            'release_id' => $batch['release_id'],
            'as_of' => $batch['as_of'],
            'market_date' => $marketDate,
        ];
        $payload = $eventIdentity + [
            'batch_finished_at' => $batch['batch_finished_at'],
            'calculation_date' => $batch['calculation_date'],
            'pipeline_version' => $batch['pipeline_version'],
            'mapping_sha256' => $mappingHash,
            'scope_manifest_sha256' => $scopeManifestHash,
            'predictions' => $batch['predictions'],
        ];
        $candidate = [
            'source_system' => 'serving_prediction_batch',
            'provider_name' => 'serving_prediction',
            'market_date' => $marketDate,
            'as_of' => $batch['as_of'],
            'source_event_key' => $this->canonicalizer->sha256($eventIdentity),
            'payload_sha256' => $this->canonicalizer->sha256($payload),
            'mapping_sha256' => $mappingHash,
            'scope_manifest_sha256' => $scopeManifestHash,
            'outbox_sequence_no' => $outboxSequence,
            'outbox_payload_sha256' => $outboxPayloadHash,
            'batch_id' => $batch['batch_id'],
            'release_id' => $batch['release_id'],
            'batch_status' => 'complete',
            // This is derived only after the four authoritative scope fields
            // above were checked, never accepted from a caller assertion.
            'scope_active' => true,
            'serving_instrument_id' => $sourceInstrumentId,
        ];

        $sessions = $this->priorSessions(
            $priorSnapshot,
            $localInstrumentId,
            $sourceInstrumentId,
            $mappingHash,
            $coverageStart,
            $verifiedThrough,
            $timezone,
        );
        $last = $sessions === [] ? null : $sessions[array_key_last($sessions)];
        if ($last !== null) {
            $lastAsOf = $this->canonicalizer->dateTime($last['as_of']);
            if ($marketDate < (string) $last['market_date'] || $asOf < $lastAsOf) {
                throw new LogicException('SESSION_EVIDENCE_NOT_APPEND_ONLY');
            }
        }
        if ($last === null || $marketDate > (string) $last['market_date']) {
            if ($last !== null && $asOf <= $this->canonicalizer->dateTime($last['as_of'])) {
                throw new LogicException('SESSION_EVIDENCE_CHRONOLOGY_INVALID');
            }
            $sessions[] = $candidate;
        }

        return [
            'provider_name' => 'serving_prediction',
            'resolver_version' => FinalEntryRawPredictionResolver::SESSION_RESOLVER_VERSION,
            'local_instrument_id' => $localInstrumentId,
            'source_instrument_id' => $sourceInstrumentId,
            'mapping_sha256' => $mappingHash,
            'session_timezone' => $timezoneName,
            'heartbeat_at' => $this->canonicalizer->databaseTimestamp($heartbeatAt),
            'verified_through_as_of' => $this->canonicalizer->databaseTimestamp($verifiedThrough),
            'verified_through_batch_id' => $batch['batch_id'],
            'verified_through_sequence_no' => $outboxSequence,
            'verified_through_outbox_payload_sha256' => $outboxPayloadHash,
            'verified_through_batch_completed_at' => $batch['batch_finished_at'],
            'verified_through_scope_manifest_sha256' => $scopeManifestHash,
            'coverage_start_as_of' => $this->canonicalizer->databaseTimestamp($coverageStart),
            'gap_free' => true,
            'sessions' => $sessions,
        ];
    }

    /**
     * @param  array<string,mixed>|null  $snapshot
     * @return list<array<string,mixed>>
     */
    private function priorSessions(
        ?array $snapshot,
        int $localInstrumentId,
        int $sourceInstrumentId,
        string $mappingHash,
        DateTimeImmutable $coverageStart,
        DateTimeImmutable $verifiedThrough,
        DateTimeZone $timezone,
    ): array {
        if ($snapshot === null) {
            return [];
        }
        $priorCoverageStart = $this->canonicalizer->dateTime(
            $snapshot['coverage_start_as_of'] ?? null,
        );
        if (array_is_list($snapshot)
            || ($snapshot['provider_name'] ?? null) !== 'serving_prediction'
            || ($snapshot['resolver_version'] ?? null)
                !== FinalEntryRawPredictionResolver::SESSION_RESOLVER_VERSION
            || (int) ($snapshot['local_instrument_id'] ?? 0) !== $localInstrumentId
            || (int) ($snapshot['source_instrument_id'] ?? 0) !== $sourceInstrumentId
            || ($snapshot['mapping_sha256'] ?? null) !== $mappingHash
            || ($snapshot['session_timezone'] ?? null) !== $timezone->getName()
            || ! $this->databaseBool($snapshot['gap_free'] ?? null)
            || filter_var(
                $snapshot['verified_through_sequence_no'] ?? null,
                FILTER_VALIDATE_INT,
            ) === false
            || (int) ($snapshot['verified_through_sequence_no'] ?? 0) <= 0
            || preg_match(
                '/^[0-9a-f]{64}$/',
                strtolower(trim((string) (
                    $snapshot['verified_through_outbox_payload_sha256'] ?? ''
                ))),
            ) !== 1
            || $priorCoverageStart > $coverageStart
            || ! isset($snapshot['verified_through_as_of'])
            || $this->canonicalizer->dateTime($snapshot['verified_through_as_of'])
                > $verifiedThrough
            || ! isset($snapshot['sessions'])
            || ! is_array($snapshot['sessions'])) {
            throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
        }

        $sessions = [];
        $dates = [];
        $events = [];
        $batches = [];
        $sequences = [];
        $lastAsOf = null;
        foreach ($snapshot['sessions'] as $session) {
            if (! is_array($session) || array_is_list($session)) {
                throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
            }
            $asOf = $this->timestamp($session, 'as_of');
            $date = trim((string) ($session['market_date'] ?? ''));
            $event = strtolower(trim((string) ($session['source_event_key'] ?? '')));
            $payload = strtolower(trim((string) ($session['payload_sha256'] ?? '')));
            $scopeManifest = strtolower(trim((string) (
                $session['scope_manifest_sha256'] ?? ''
            )));
            $batch = trim((string) ($session['batch_id'] ?? ''));
            $sequence = filter_var(
                $session['outbox_sequence_no'] ?? null,
                FILTER_VALIDATE_INT,
            );
            $outboxPayload = strtolower(trim((string) (
                $session['outbox_payload_sha256'] ?? ''
            )));
            if (($session['source_system'] ?? null) !== 'serving_prediction_batch'
                || ($session['provider_name'] ?? null) !== 'serving_prediction'
                || ($session['mapping_sha256'] ?? null) !== $mappingHash
                || (int) ($session['serving_instrument_id'] ?? 0) !== $sourceInstrumentId
                || ($session['batch_status'] ?? null) !== 'complete'
                || ! $this->databaseBool($session['scope_active'] ?? null)
                || trim((string) ($session['release_id'] ?? '')) === ''
                || $batch === ''
                || $sequence === false || $sequence <= 0
                || preg_match('/^[0-9a-f]{64}$/', $outboxPayload) !== 1
                || preg_match('/^[0-9a-f]{64}$/', $event) !== 1
                || preg_match('/^[0-9a-f]{64}$/', $payload) !== 1
                || preg_match('/^[0-9a-f]{64}$/', $scopeManifest) !== 1
                || $date !== $asOf->setTimezone($timezone)->format('Y-m-d')
                || $asOf < $priorCoverageStart
                || $asOf > $verifiedThrough
                || isset($dates[$date]) || isset($events[$event]) || isset($batches[$batch])
                || isset($sequences[$sequence])
                || ($lastAsOf !== null && $asOf <= $lastAsOf)) {
                throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
            }
            $dates[$date] = true;
            $events[$event] = true;
            $batches[$batch] = true;
            $sequences[$sequence] = true;
            $lastAsOf = $asOf;
            if ($asOf >= $coverageStart) {
                $sessions[] = $session;
            }
        }

        return $sessions;
    }

    /** @param array<string,mixed> $row */
    private function positiveInt(array $row, string $key): int
    {
        $value = filter_var($row[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value <= 0) {
            throw new LogicException(strtoupper($key).'_INVALID');
        }

        return (int) $value;
    }

    /** @param array<string,mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value === '') {
            throw new LogicException(strtoupper($key).'_MISSING');
        }

        return $value;
    }

    /** @param array<string,mixed> $row */
    private function timestamp(array $row, string $key): DateTimeImmutable
    {
        try {
            return $this->canonicalizer->dateTime($row[$key] ?? null);
        } catch (\Throwable) {
            throw new LogicException(strtoupper($key).'_INVALID');
        }
    }

    /** @param array<string,mixed> $row */
    private function date(array $row, string $key): string
    {
        $value = trim((string) ($row[$key] ?? ''));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new LogicException(strtoupper($key).'_INVALID');
        }

        return $value;
    }

    private function jsonValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        try {
            return json_decode($value, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new LogicException('SESSION_SOURCE_CONTEXT_INVALID');
        }
    }

    private function databaseBool(mixed $value): bool
    {
        return match (true) {
            $value === true, $value === 1, $value === '1', $value === 't' => true,
            $value === false, $value === 0, $value === '0', $value === 'f' => false,
            default => throw new LogicException('AUTHORITATIVE_BOOLEAN_INVALID'),
        };
    }

    private function sameInstant(mixed $left, mixed $right): bool
    {
        try {
            return $this->canonicalizer->utcTimestamp(
                $this->canonicalizer->dateTime($left),
            ) === $this->canonicalizer->utcTimestamp(
                $this->canonicalizer->dateTime($right),
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
