<?php

namespace App\Services;

use App\Data\FinalEntrySignalDecisionInput;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Assesses settled completed serving-batch snapshots with the current entry
 * policy and writes only the FINAL-entry shadow ledger. Legacy/dashboard/
 * email/automation consumers remain untouched.
 */
class FinalEntryShadowWriter
{
    public const WORKER_KEY = 'final-entry-shadow-v1';

    private const SOURCE_CONTRACT_VERSION = 'serving-prediction-terminal-v1';

    private const SOURCE_STREAM_KEY = 'stock-final-entry';

    public function __construct(
        private readonly FinalEntrySignalDecisionService $decisions,
        private readonly FinalEntryRawPredictionResolver $rawResolver,
        private readonly FinalEntrySessionEvidenceBuilder $evidenceBuilder,
        private readonly FinalEntryCanonicalizer $canonicalizer,
    ) {}

    /** @return array<string,mixed> */
    public function run(int $maxBatches = 1): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('FINAL_ENTRY_SHADOW_REQUIRES_POSTGRESQL');
        }
        $this->rawResolver->resetRuntimeCache();
        $this->decisions->resetRuntimeCache();
        $maxBatches = max(1, min(50, $maxBatches));
        $runtime = $this->runtimeConfig();
        $this->initializeWatermark($runtime);

        $summary = [
            'worker' => self::WORKER_KEY,
            'evaluator_version' => (string) $runtime['evaluator_version'],
            'cutover_at' => (string) $runtime['cutover_at'],
            'requested_batch_limit' => $maxBatches,
            'processed_batch_count' => 0,
            'batches' => [],
        ];

        for ($index = 0; $index < $maxBatches; $index++) {
            $batchSummary = DB::transaction(function () use ($runtime): ?array {
                $watermark = $this->lockedWatermark($runtime);
                $batch = $this->nextOutboxEnvelope($watermark, $runtime);
                if ($batch === null) {
                    return null;
                }

                $result = $this->processBatch((array) $batch, $runtime);
                DB::table('entry_signal_shadow_watermarks')
                    ->where('worker_key', self::WORKER_KEY)
                    ->update([
                        'last_sequence_no' => (int) $batch->sequence_no,
                        'last_batch_id' => (string) $batch->batch_id,
                        'last_batch_completed_at' => (string) $batch->finished_at,
                        'last_calculation_date' => (string) $batch->calculation_date,
                        'last_payload_sha256' => trim((string) $batch->payload_sha256),
                        'processed_batches' => ((int) $watermark->processed_batches) + 1,
                        'last_summary' => $this->canonicalizer->json($result),
                        'updated_at' => $result['completed_at'],
                    ]);

                return $result;
            }, 3);

            if ($batchSummary === null) {
                break;
            }
            $summary['processed_batch_count']++;
            $summary['batches'][] = $batchSummary;
        }

        $summary['idle'] = $summary['processed_batch_count'] === 0;
        $idleActivity = $summary['idle']
            ? DB::transaction(function () use ($runtime): array {
                $watermark = $this->lockedWatermark($runtime);

                return $this->refreshIdleFeeds($watermark);
            }, 3)
            : ['refreshed' => 0, 'staled' => 0];
        $summary['idle_heartbeat_refresh_count'] = $idleActivity['refreshed'];
        $summary['idle_feed_stale_count'] = $idleActivity['staled'];

        return $summary;
    }

    /** @return array<string,mixed> */
    private function runtimeConfig(): array
    {
        $rows = DB::table('entry_signal_runtime_config')
            ->where('singleton', true)
            ->limit(2)
            ->get(['evaluator_version', 'cutover_at']);
        if ($rows->count() !== 1
            || (string) $rows->first()->evaluator_version
                !== FinalEntrySignalDecisionService::EVALUATOR_VERSION) {
            throw new LogicException('ENTRY_RUNTIME_CONFIG_INVALID');
        }

        return (array) $rows->first();
    }

    /** @param array<string,mixed> $runtime */
    private function initializeWatermark(array $runtime): void
    {
        $now = $this->databaseClock();
        DB::table('entry_signal_shadow_watermarks')->insertOrIgnore([
            'worker_key' => self::WORKER_KEY,
            'evaluator_version' => (string) $runtime['evaluator_version'],
            'cutover_at' => (string) $runtime['cutover_at'],
            'source_contract_version' => self::SOURCE_CONTRACT_VERSION,
            'source_stream_key' => self::SOURCE_STREAM_KEY,
            'last_sequence_no' => 0,
            'processed_batches' => 0,
            'last_summary' => '{}',
            'created_at' => $this->canonicalizer->databaseTimestamp($now),
            'updated_at' => $this->canonicalizer->databaseTimestamp($now),
        ]);
    }

    /** @param array<string,mixed> $runtime */
    private function lockedWatermark(array $runtime): object
    {
        $row = DB::table('entry_signal_shadow_watermarks')
            ->where('worker_key', self::WORKER_KEY)
            ->lockForUpdate()
            ->first();
        if ($row === null
            || (string) $row->evaluator_version !== (string) $runtime['evaluator_version']
            || ! $this->sameInstant($row->cutover_at, $runtime['cutover_at'])
            || (string) ($row->source_contract_version ?? '')
                !== self::SOURCE_CONTRACT_VERSION
            || (string) ($row->source_stream_key ?? '') !== self::SOURCE_STREAM_KEY) {
            throw new LogicException('ENTRY_SHADOW_WATERMARK_INVALID');
        }
        $sequence = (int) ($row->last_sequence_no ?? 0);
        if ($sequence === 0) {
            if ((int) ($row->processed_batches ?? -1) !== 0) {
                throw new LogicException('ENTRY_SHADOW_WATERMARK_INVALID');
            }
        } else {
            $ledgerExists = DB::table('entry_signal_shadow_processed_batches')
                ->where('sequence_no', $sequence)
                ->where('batch_id', (string) ($row->last_batch_id ?? ''))
                ->where(
                    'payload_sha256',
                    strtolower(trim((string) ($row->last_payload_sha256 ?? ''))),
                )
                ->exists();
            if (! $ledgerExists || (int) $row->processed_batches !== $sequence) {
                throw new LogicException('ENTRY_SHADOW_WATERMARK_INVALID');
            }
        }

        return $row;
    }

    /**
     * The source outbox owns commit order. Its transactional counter is
     * gap-free, so any missing successor is a hard source-contract failure.
     *
     * @param  array<string,mixed>  $runtime
     */
    private function nextOutboxEnvelope(object $watermark, array $runtime): ?object
    {
        $lastSequence = (int) ($watermark->last_sequence_no ?? 0);
        $rows = DB::connection('serving')
            ->table('serving_prediction_batch_outbox as outbox')
            ->join(
                'serving_prediction_batch_outbox_integrity as integrity',
                'integrity.sequence_no',
                '=',
                'outbox.sequence_no',
            )
            ->where('outbox.stream_key', self::SOURCE_STREAM_KEY)
            ->where('outbox.sequence_no', '>', $lastSequence)
            ->orderBy('outbox.sequence_no')
            ->limit(2)
            ->get([
                'outbox.sequence_no', 'outbox.contract_version',
                'outbox.stream_key', 'outbox.batch_id',
                'outbox.calculation_date', 'outbox.pipeline_version',
                'outbox.status', 'outbox.expected_count',
                'outbox.completed_count', 'outbox.failed_count',
                'outbox.stored_prediction_count', 'outbox.expected_scope_count',
                'outbox.expected_stock_scope_count',
                'outbox.expected_stock_instrument_count',
                'outbox.error_summary', 'outbox.started_at',
                'outbox.finished_at', 'outbox.published_at',
                'outbox.prediction_manifest', 'outbox.expected_scope_manifest',
                'outbox.release_manifest', 'outbox.header_sha256',
                'outbox.prediction_manifest_sha256',
                'outbox.expected_scope_manifest_sha256',
                'outbox.release_manifest_sha256', 'outbox.request_sha256',
                'outbox.payload_sha256', 'integrity.prediction_count_valid',
                'integrity.scope_count_valid', 'integrity.prediction_hash_valid',
                'integrity.scope_hash_valid', 'integrity.release_hash_valid',
                'integrity.payload_hash_valid',
            ]);
        if ($rows->isEmpty()) {
            return null;
        }

        $outbox = $rows->first();
        if ((int) $outbox->sequence_no !== $lastSequence + 1) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_SEQUENCE_GAP');
        }
        $this->assertOutboxEnvelope((array) $outbox, $runtime);

        return $outbox;
    }

    /**
     * @param  array<string,mixed>  $outbox
     * @param  array<string,mixed>  $runtime
     */
    private function assertOutboxEnvelope(array $outbox, array $runtime): void
    {
        if (($outbox['contract_version'] ?? null) !== self::SOURCE_CONTRACT_VERSION
            || ($outbox['stream_key'] ?? null) !== self::SOURCE_STREAM_KEY) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_CONTRACT_INVALID');
        }
        foreach ([
            'prediction_count_valid', 'scope_count_valid',
            'prediction_hash_valid', 'scope_hash_valid',
            'release_hash_valid', 'payload_hash_valid',
        ] as $flag) {
            if (! $this->databaseBool($outbox[$flag] ?? null)) {
                throw new LogicException('ENTRY_SHADOW_OUTBOX_INTEGRITY_INVALID');
            }
        }
        foreach ([
            'header_sha256', 'prediction_manifest_sha256',
            'expected_scope_manifest_sha256', 'release_manifest_sha256',
            'request_sha256', 'payload_sha256',
        ] as $hash) {
            if (preg_match(
                '/^[0-9a-f]{64}$/',
                strtolower(trim((string) ($outbox[$hash] ?? ''))),
            ) !== 1) {
                throw new LogicException('ENTRY_SHADOW_OUTBOX_INTEGRITY_INVALID');
            }
        }

        $expected = filter_var($outbox['expected_count'] ?? null, FILTER_VALIDATE_INT);
        $completed = filter_var($outbox['completed_count'] ?? null, FILTER_VALIDATE_INT);
        $failed = filter_var($outbox['failed_count'] ?? null, FILTER_VALIDATE_INT);
        $stored = filter_var(
            $outbox['stored_prediction_count'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $scopeCount = filter_var(
            $outbox['expected_scope_count'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $stockScopeCount = filter_var(
            $outbox['expected_stock_scope_count'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $stockInstrumentCount = filter_var(
            $outbox['expected_stock_instrument_count'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $errors = $this->jsonArray($outbox['error_summary'] ?? null);
        if (($outbox['status'] ?? null) !== 'complete') {
            throw new LogicException('ENTRY_SHADOW_SOURCE_BATCH_FAILED');
        }
        if ($expected === false || $expected <= 0
            || $completed === false || $completed !== $expected
            || $failed === false || $failed !== 0
            || $stored === false || $stored !== $completed
            || $scopeCount === false || $scopeCount !== $expected
            || $stockScopeCount === false || $stockScopeCount !== $scopeCount
            || $stockInstrumentCount === false || $stockInstrumentCount <= 0
            || $errors !== []) {
            throw new LogicException('ENTRY_SHADOW_BATCH_COUNTERS_INCOMPLETE');
        }
        $predictions = $this->jsonArray($outbox['prediction_manifest'] ?? null);
        $scopes = $this->jsonArray($outbox['expected_scope_manifest'] ?? null);
        $releases = $this->jsonArray($outbox['release_manifest'] ?? null);
        if (count($predictions) !== $stored
            || count($scopes) !== $scopeCount
            || $releases === []) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_MANIFEST_INCOMPLETE');
        }

        $cutover = $this->canonicalizer->dateTime($runtime['cutover_at'] ?? null);
        $finished = $this->canonicalizer->dateTime($outbox['finished_at'] ?? null);
        $published = $this->canonicalizer->dateTime($outbox['published_at'] ?? null);
        if ($finished < $cutover || $published < $finished) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_OUTSIDE_CUTOVER');
        }
    }

    /** @return list<mixed> */
    private function jsonArray(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 256, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new LogicException('ENTRY_SHADOW_OUTBOX_JSON_INVALID');
            }
        }
        if (! is_array($value) || ! array_is_list($value)) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_JSON_INVALID');
        }

        return $value;
    }

    /**
     * @param  array<string,mixed>  $batch
     * @param  array<string,mixed>  $runtime
     * @return array<string,mixed>
     */
    private function processBatch(
        array $batch,
        array $runtime,
    ): array {
        $batchId = trim((string) ($batch['batch_id'] ?? ''));
        if ($batchId === '') {
            throw new LogicException('ENTRY_SHADOW_BATCH_ID_MISSING');
        }
        $heartbeat = $this->databaseClock();
        $frozen = $this->frozenOutboxBatch($batch);
        $sourceRows = $frozen['rows'];
        $coverage = $frozen['coverage'];
        $this->recordProcessedBatch($batch, $frozen, $heartbeat);
        // Batch-scoped source caches prevent a prior immutable snapshot from
        // masking a changed instrument identity in a later Outbox sequence.
        $this->rawResolver->resetRuntimeCache();
        $this->rawResolver->primeOutboxBatch($sourceRows);

        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id'])
            ->all();
        $savedFilters = DB::table('saved_prediction_filters as saved_filter')
            ->join('users as user', 'user.id', '=', 'saved_filter.user_id')
            ->whereNull('user.deleted_at')
            ->orderBy('saved_filter.user_id')
            ->orderBy('saved_filter.id')
            ->get(['saved_filter.id', 'saved_filter.user_id'])
            ->all();
        $contexts = [];
        foreach ($users as $user) {
            $contexts[] = [
                'user_id' => (int) $user->id,
                'context_type' => 'user_profile',
                'saved_prediction_filter_id' => null,
            ];
        }
        foreach ($savedFilters as $savedFilter) {
            $contexts[] = [
                'user_id' => (int) $savedFilter->user_id,
                'context_type' => 'saved_filter',
                'saved_prediction_filter_id' => (int) $savedFilter->id,
            ];
        }

        $rowsByInstrument = [];
        foreach ($sourceRows as $row) {
            $rowsByInstrument[(int) $row['instrument_id']][] = $row;
        }
        ksort($rowsByInstrument, SORT_NUMERIC);

        $staledMissingFeeds = $this->staleFeedsMissingFromBatch(
            array_keys($rowsByInstrument),
            $heartbeat,
        );

        $counts = [
            'stock_instruments' => count($rowsByInstrument),
            'mapped_instruments' => 0,
            'unmapped_instruments' => 0,
            'user_profile_contexts' => count($users),
            'saved_filter_contexts' => count($savedFilters),
            'total_contexts' => count($contexts),
            'source_raw_events' => count($sourceRows),
            'eligible_raw_events' => count($sourceRows),
            'raw_buy_events' => count(array_filter(
                $sourceRows,
                static fn (array $row): bool => strtoupper((string) ($row['signal'] ?? '')) === 'BUY',
            )),
            'evaluated_context_events' => 0,
            'accepted_events' => 0,
            'feed_updates' => 0,
            'feeds_staled_missing_scope' => $staledMissingFeeds,
            'lifecycle_advances' => 0,
            'lifecycle_terminals' => 0,
            'decisions' => 0,
            'idempotent_replays' => 0,
            'statuses' => [],
        ];
        foreach ($rowsByInstrument as $sourceInstrumentId => $currentRows) {
            try {
                $mapping = $this->rawResolver
                    ->resolveOutboxInstrumentMapping($currentRows[0]);
            } catch (LogicException $exception) {
                // Never move the global cursor past an event that could not
                // be mapped. The complete transaction is retried after the
                // mapping is repaired.
                throw new LogicException(
                    $this->safeCode(
                        $exception->getMessage(),
                        'INSTRUMENT_MAPPING_FAILED',
                    ),
                    0,
                    $exception,
                );
            }
            $counts['mapped_instruments']++;
            $localInstrumentId = (int) $mapping['local_instrument_id'];
            $verifiedThrough = $this->maximumAsOf($currentRows);
            $coverageStart = $this->coverageStartForInstrument(
                $localInstrumentId,
                $verifiedThrough,
                $runtime,
            );
            $priorSnapshot = $this->priorFeedSnapshot($mapping);
            $priorSnapshot = $this->priorSnapshotForSequence(
                $priorSnapshot,
                (int) $batch['sequence_no'],
                $coverageStart,
                $verifiedThrough,
            );
            $snapshot = $this->evidenceBuilder->build(
                $currentRows,
                $mapping,
                $coverageStart,
                $verifiedThrough,
                $heartbeat,
                $priorSnapshot,
                true,
            );
            $this->materializeSessionEvents($snapshot, $heartbeat);
            $this->recordReadyFeed($mapping, $snapshot, $heartbeat, $verifiedThrough);
            $counts['feed_updates']++;

            $advanced = $this->advanceActiveLifecycles(
                $localInstrumentId,
                $heartbeat,
            );
            $counts['lifecycle_advances'] += $advanced['advanced'];
            $counts['lifecycle_terminals'] += $advanced['terminal'];

            $buyRows = array_values(array_filter(
                $currentRows,
                static fn (array $row): bool => strtoupper((string) ($row['signal'] ?? '')) === 'BUY',
            ));
            usort($buyRows, fn (array $left, array $right): int => $this->predictionPriority($left) <=> $this->predictionPriority($right));
            // The immutable source ledger covers every raw event. User filter
            // decisions are relevant only for raw BUY; a HOLD cannot be
            // promoted. Every BUY is still evaluated for every context.
            foreach ($buyRows as $prediction) {
                foreach ($contexts as $context) {
                    $counts['evaluated_context_events']++;
                    $result = $this->decisions->evaluate(
                        new FinalEntrySignalDecisionInput(
                            userId: $context['user_id'],
                            instrumentId: $localInstrumentId,
                            sourcePredictionId: (int) $prediction['prediction_id'],
                            contextType: $context['context_type'],
                            savedPredictionFilterId: $context['saved_prediction_filter_id'],
                        ),
                    );
                    if (! $result->persistenceReady) {
                        throw new LogicException('SHADOW_DECISION_NOT_PERSISTABLE');
                    }
                    $status = (string) ($result->decision['decision_status'] ?? 'ERROR');
                    $counts['decisions']++;
                    $counts['statuses'][$status] = ($counts['statuses'][$status] ?? 0) + 1;
                    if ($result->idempotentReplay) {
                        $counts['idempotent_replays']++;
                    }
                    if ($result->isAccepted()) {
                        $counts['accepted_events']++;
                    }
                }
            }
        }
        ksort($counts['statuses'], SORT_STRING);

        $this->assertOutboxStillCurrent($batch);

        $completedAt = $this->canonicalizer->databaseTimestamp($this->databaseClock());

        return [
            'sequence_no' => (int) $batch['sequence_no'],
            'batch_id' => $batchId,
            'batch_completed_at' => (string) $batch['finished_at'],
            'calculation_date' => (string) $batch['calculation_date'],
            'pipeline_version' => (string) $batch['pipeline_version'],
            'contract_version' => self::SOURCE_CONTRACT_VERSION,
            'stream_key' => self::SOURCE_STREAM_KEY,
            'header_sha256' => trim((string) $batch['header_sha256']),
            'prediction_manifest_sha256' => trim((string) (
                $batch['prediction_manifest_sha256']
            )),
            'expected_scope_manifest_sha256' => $coverage['sha256'],
            'release_manifest_sha256' => trim((string) (
                $batch['release_manifest_sha256']
            )),
            'request_sha256' => trim((string) $batch['request_sha256']),
            'payload_sha256' => trim((string) $batch['payload_sha256']),
            'counts' => $counts,
            'processed_batch_ledger_recorded' => true,
            'shadow_only' => true,
            'completed_at' => $completedAt,
        ];
    }

    /**
     * Validate the frozen parent manifests and materialize the typed immutable
     * event view into the row shape consumed by the evaluator/feed builder.
     *
     * @param  array<string,mixed>  $batch
     * @return array{rows:list<array<string,mixed>>,coverage:array{count:int,instrument_count:int,sha256:string},release_count:int}
     */
    private function frozenOutboxBatch(array $batch): array
    {
        $batchId = $this->requiredString($batch, 'batch_id');
        $sequence = $this->positiveInt($batch, 'sequence_no');
        $manifestHash = $this->hash64(
            $batch['expected_scope_manifest_sha256'] ?? null,
        );
        $outboxPayloadHash = $this->hash64($batch['payload_sha256'] ?? null);
        $predictionManifest = $this->jsonArray($batch['prediction_manifest'] ?? null);
        $expectedScopes = $this->jsonArray($batch['expected_scope_manifest'] ?? null);
        $releaseManifest = $this->jsonArray($batch['release_manifest'] ?? null);

        $eventRows = DB::connection('serving')
            ->table('serving_prediction_batch_outbox_events')
            ->where('stream_key', self::SOURCE_STREAM_KEY)
            ->where('sequence_no', $sequence)
            ->orderBy('instrument_id')
            ->orderBy('horizon')
            ->orderBy('variant')
            ->orderBy('prediction_id')
            ->get([
                'prediction_snapshot', 'payload_sha256',
                'expected_scope_manifest_sha256',
            ]);
        $viewManifest = [];
        foreach ($eventRows as $eventRow) {
            if (! hash_equals(
                $outboxPayloadHash,
                $this->hash64($eventRow->payload_sha256 ?? null),
            ) || ! hash_equals(
                $manifestHash,
                $this->hash64($eventRow->expected_scope_manifest_sha256 ?? null),
            )) {
                throw new LogicException('ENTRY_SHADOW_OUTBOX_EVENT_BINDING_INVALID');
            }
            $viewManifest[] = $this->jsonObject($eventRow->prediction_snapshot ?? null);
        }
        if (count($viewManifest) !== (int) ($batch['stored_prediction_count'] ?? -1)
            || ! hash_equals(
                $this->canonicalizer->sha256($predictionManifest),
                $this->canonicalizer->sha256($viewManifest),
            )) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_EVENT_VIEW_MISMATCH');
        }

        $releases = [];
        foreach ($releaseManifest as $release) {
            $release = $this->jsonObject($release);
            $releaseId = $this->requiredString($release, 'release_id');
            $instrumentId = $this->positiveInt($release, 'instrument_id');
            $contentHash = $this->hash64($release['content_sha256'] ?? null);
            if (isset($releases[$releaseId])) {
                throw new LogicException('ENTRY_SHADOW_RELEASE_MANIFEST_DUPLICATE');
            }
            foreach (['compact_metrics_sha256', 'artifact_manifest_sha256'] as $hash) {
                $this->hash64($release[$hash] ?? null);
            }
            foreach (['pipeline_version', 'source_commit', 'dataset_cutoff', 'input_fingerprint'] as $key) {
                $this->requiredString($release, $key);
            }
            $releases[$releaseId] = [
                'instrument_id' => $instrumentId,
                'content_sha256' => $contentHash,
            ];
        }

        $scopes = [];
        $instrumentSnapshots = [];
        foreach ($expectedScopes as $scope) {
            $scope = $this->jsonObject($scope);
            $instrumentId = $this->positiveInt($scope, 'instrument_id');
            $releaseId = $this->requiredString($scope, 'release_id');
            $horizon = $this->positiveInt($scope, 'horizon');
            $variant = $this->requiredString($scope, 'variant');
            $tuple = $this->scopeTuple($instrumentId, $releaseId, $horizon, $variant);
            $releaseHash = $this->hash64($scope['release_content_sha256'] ?? null);
            $release = $releases[$releaseId] ?? null;
            $selected = $this->databaseBool($scope['selected_for_prediction'] ?? null);
            if (isset($scopes[$tuple]) || $release === null
                || $release['instrument_id'] !== $instrumentId
                || ! hash_equals($release['content_sha256'], $releaseHash)
                || ($scope['instrument_type'] ?? null) !== 'stock'
                || ! $this->databaseBool($scope['prediction_enabled'] ?? null)
                || ($scope['prediction_status'] ?? null) !== 'eligible') {
                throw new LogicException('ENTRY_SHADOW_SCOPE_MANIFEST_INVALID');
            }
            $normalizedScope = [
                'selected_for_prediction' => $selected,
                'prediction_status' => 'eligible',
                'prediction_enabled' => true,
                'entry_policy' => $this->jsonObject($scope['entry_policy'] ?? null, true),
                'performance' => $this->jsonObject($scope['performance'] ?? null, true),
                'model_quality_class' => $this->requiredString($scope, 'model_quality_class'),
                'model_quality_label' => $this->requiredString($scope, 'model_quality_label'),
                'quality_gate_passed' => $this->databaseBool(
                    $scope['quality_gate_passed'] ?? null,
                ),
            ];
            $instrument = [
                'instrument_id' => $instrumentId,
                'symbol' => $this->requiredString($scope, 'symbol'),
                'provider_symbol' => $this->requiredString($scope, 'provider_symbol'),
                'isin' => $scope['isin'] ?? null,
                'exchange' => $this->requiredString($scope, 'exchange'),
                'country_code' => $scope['country_code'] ?? null,
                'sector_code' => $scope['sector_code'] ?? null,
                'instrument_type' => 'stock',
            ];
            if (isset($instrumentSnapshots[$instrumentId])
                && ! hash_equals(
                    $this->canonicalizer->sha256($instrumentSnapshots[$instrumentId]),
                    $this->canonicalizer->sha256($instrument),
                )) {
                throw new LogicException('ENTRY_SHADOW_INSTRUMENT_SNAPSHOT_INCONSISTENT');
            }
            $instrumentSnapshots[$instrumentId] = $instrument;
            $scopes[$tuple] = [
                'instrument' => $instrument,
                'scope' => $normalizedScope,
                'release_content_sha256' => $releaseHash,
            ];
        }

        $rows = [];
        $seenPredictions = [];
        $seenScopes = [];
        foreach ($viewManifest as $prediction) {
            $predictionId = $this->positiveInt($prediction, 'prediction_id');
            $instrumentId = $this->positiveInt($prediction, 'instrument_id');
            $releaseId = $this->requiredString($prediction, 'release_id');
            $horizon = $this->positiveInt($prediction, 'horizon');
            $variant = $this->requiredString($prediction, 'variant');
            $tuple = $this->scopeTuple($instrumentId, $releaseId, $horizon, $variant);
            $expected = $scopes[$tuple] ?? null;
            if (isset($seenPredictions[$predictionId]) || isset($seenScopes[$tuple])
                || $expected === null
                || $this->requiredString($prediction, 'batch_id') !== $batchId
                || ! hash_equals(
                    $expected['release_content_sha256'],
                    $this->hash64($prediction['release_content_sha256'] ?? null),
                )) {
                throw new LogicException('ENTRY_SHADOW_PREDICTION_MANIFEST_INVALID');
            }
            $instrument = $this->jsonObject($prediction['instrument'] ?? null);
            $predictionScope = $this->jsonObject($prediction['scope'] ?? null);
            $eventInstrument = [
                'instrument_id' => $this->positiveInt($instrument, 'instrument_id'),
                'symbol' => $this->requiredString($instrument, 'symbol'),
                'provider_symbol' => $this->requiredString($instrument, 'provider_symbol'),
                'isin' => $instrument['isin'] ?? null,
                'exchange' => $this->requiredString($instrument, 'exchange'),
                'country_code' => $instrument['country_code'] ?? null,
                'sector_code' => $instrument['sector_code'] ?? null,
                'instrument_type' => $instrument['instrument_type'] ?? null,
            ];
            $trainingSymbol = strtoupper($this->requiredString(
                $instrument,
                'training_provider_symbol',
            ));
            if ($eventInstrument['instrument_id'] !== $instrumentId
                || $eventInstrument['instrument_type'] !== 'stock'
                || $trainingSymbol !== strtoupper($expected['instrument']['provider_symbol'])
                || $eventInstrument['symbol'] !== $expected['instrument']['symbol']
                || $eventInstrument['isin'] !== $expected['instrument']['isin']
                || $eventInstrument['exchange'] !== $expected['instrument']['exchange']
                || $eventInstrument['country_code'] !== $expected['instrument']['country_code']
                || $eventInstrument['sector_code'] !== $expected['instrument']['sector_code']
                || ! hash_equals(
                    $this->canonicalizer->sha256($expected['scope']),
                    $this->canonicalizer->sha256([
                        'selected_for_prediction' => $this->databaseBool(
                            $predictionScope['selected_for_prediction'] ?? null,
                        ),
                        'prediction_status' => $predictionScope['prediction_status'] ?? null,
                        'prediction_enabled' => $this->databaseBool(
                            $predictionScope['prediction_enabled'] ?? null,
                        ),
                        'entry_policy' => $this->jsonObject(
                            $predictionScope['entry_policy'] ?? null,
                            true,
                        ),
                        'performance' => $this->jsonObject(
                            $predictionScope['performance'] ?? null,
                            true,
                        ),
                        'model_quality_class' => $predictionScope['model_quality_class'] ?? null,
                        'model_quality_label' => $predictionScope['model_quality_label'] ?? null,
                        'quality_gate_passed' => $this->databaseBool(
                            $predictionScope['quality_gate_passed'] ?? null,
                        ),
                    ]),
                )) {
                throw new LogicException('ENTRY_SHADOW_PREDICTION_SCOPE_NOT_FROZEN');
            }
            $signal = strtoupper($this->requiredString($prediction, 'signal'));
            if (! in_array($signal, ['BUY', 'HOLD', 'WATCH', 'WAIT', 'SELL'], true)) {
                throw new LogicException('ENTRY_SHADOW_PREDICTION_SIGNAL_INVALID');
            }

            $seenPredictions[$predictionId] = true;
            $seenScopes[$tuple] = true;
            $rows[] = [
                'id' => $predictionId,
                'prediction_id' => $predictionId,
                'batch_id' => $batchId,
                'instrument_id' => $instrumentId,
                'release_id' => $releaseId,
                'as_of' => $this->requiredString($prediction, 'as_of'),
                'horizon' => $horizon,
                'variant' => $variant,
                'signal' => $signal,
                'expected_return' => $prediction['expected_return'] ?? null,
                'target_price' => $prediction['target_price'] ?? null,
                'calibrated_score' => $prediction['calibrated_score'] ?? null,
                'risk_score' => $prediction['risk_score'] ?? null,
                'confidence' => $prediction['confidence'] ?? null,
                'compact_context' => $prediction['compact_context'] ?? null,
                'created_at' => $this->requiredString($prediction, 'created_at'),
                'batch_status' => 'complete',
                'batch_finished_at' => (string) $batch['finished_at'],
                'batch_completed_at' => (string) $batch['finished_at'],
                'calculation_date' => (string) $batch['calculation_date'],
                'batch_calculation_date' => (string) $batch['calculation_date'],
                'pipeline_version' => (string) $batch['pipeline_version'],
                'selected_for_prediction' => $expected['scope']['selected_for_prediction'],
                'prediction_enabled' => true,
                'prediction_status' => 'eligible',
                'source_release_bound' => true,
                'joined_release_id' => $releaseId,
                'release_instrument_id' => $instrumentId,
                'release_content_sha256' => $expected['release_content_sha256'],
                'model_quality_class' => $expected['scope']['model_quality_class'],
                'model_quality_label' => $expected['scope']['model_quality_label'],
                'quality_gate_passed' => $expected['scope']['quality_gate_passed'],
                'entry_policy' => $expected['scope']['entry_policy'],
                'performance' => $expected['scope']['performance'],
                'symbol' => $eventInstrument['symbol'],
                'provider_symbol' => $eventInstrument['provider_symbol'],
                'isin' => $eventInstrument['isin'],
                'exchange' => $eventInstrument['exchange'],
                'country_code' => $eventInstrument['country_code'],
                'sector_code' => $eventInstrument['sector_code'],
                'instrument_type' => 'stock',
                'scope_manifest_sha256' => $manifestHash,
                'outbox_sequence_no' => $sequence,
                'outbox_contract_version' => self::SOURCE_CONTRACT_VERSION,
                'outbox_stream_key' => self::SOURCE_STREAM_KEY,
                'outbox_payload_sha256' => $outboxPayloadHash,
            ];
        }
        if (count($seenScopes) !== count($scopes)
            || count($instrumentSnapshots)
                !== (int) ($batch['expected_stock_instrument_count'] ?? -1)) {
            throw new LogicException('ENTRY_SHADOW_BATCH_SCOPE_INCOMPLETE');
        }

        $qualityHorizons = [];
        foreach ($rows as $row) {
            $key = $row['instrument_id'].':'.$row['variant'];
            $qualityHorizons[$key][$row['horizon']] = true;
        }
        foreach ($rows as &$row) {
            $horizons = array_map(
                'intval',
                array_keys($qualityHorizons[$row['instrument_id'].':'.$row['variant']]),
            );
            sort($horizons, SORT_NUMERIC);
            $row['quality_horizons'] = $horizons;
        }
        unset($row);
        usort($rows, static fn (array $left, array $right): int => [
            $left['instrument_id'], $left['horizon'], $left['variant'], $left['id'],
        ] <=> [
            $right['instrument_id'], $right['horizon'], $right['variant'], $right['id'],
        ]);

        return [
            'rows' => $rows,
            'coverage' => [
                'count' => count($scopes),
                'instrument_count' => count($instrumentSnapshots),
                'sha256' => $manifestHash,
            ],
            'release_count' => count($releases),
        ];
    }

    /**
     * @param  array<string,mixed>  $batch
     * @param  array{rows:list<array<string,mixed>>,coverage:array{count:int,instrument_count:int,sha256:string},release_count:int}  $frozen
     */
    private function recordProcessedBatch(
        array $batch,
        array $frozen,
        DateTimeImmutable $processedAt,
    ): void {
        DB::table('entry_signal_shadow_processed_batches')->insert([
            'sequence_no' => (int) $batch['sequence_no'],
            'contract_version' => self::SOURCE_CONTRACT_VERSION,
            'stream_key' => self::SOURCE_STREAM_KEY,
            'batch_id' => (string) $batch['batch_id'],
            'calculation_date' => (string) $batch['calculation_date'],
            'pipeline_version' => (string) $batch['pipeline_version'],
            'status' => 'complete',
            'source_finished_at' => (string) $batch['finished_at'],
            'source_published_at' => (string) $batch['published_at'],
            'expected_count' => (int) $batch['expected_count'],
            'completed_count' => (int) $batch['completed_count'],
            'failed_count' => (int) $batch['failed_count'],
            'stored_prediction_count' => (int) $batch['stored_prediction_count'],
            'expected_scope_count' => (int) $batch['expected_scope_count'],
            'expected_stock_scope_count' => (int) $batch['expected_stock_scope_count'],
            'expected_stock_instrument_count' => (int) $batch['expected_stock_instrument_count'],
            'error_summary' => $this->canonicalizer->json(
                $this->jsonArray($batch['error_summary'] ?? null),
            ),
            'header_sha256' => $this->hash64($batch['header_sha256'] ?? null),
            'prediction_manifest_sha256' => $this->hash64(
                $batch['prediction_manifest_sha256'] ?? null,
            ),
            'expected_scope_manifest_sha256' => $frozen['coverage']['sha256'],
            'release_manifest_sha256' => $this->hash64(
                $batch['release_manifest_sha256'] ?? null,
            ),
            'request_sha256' => $this->hash64($batch['request_sha256'] ?? null),
            'payload_sha256' => $this->hash64($batch['payload_sha256'] ?? null),
            'processing_summary' => $this->canonicalizer->json([
                'shadow_only' => true,
                'source_event_count' => count($frozen['rows']),
                'expected_scope_count' => $frozen['coverage']['count'],
                'expected_stock_instrument_count' => $frozen['coverage']['instrument_count'],
                'release_count' => $frozen['release_count'],
            ]),
            'processed_at' => $this->canonicalizer->databaseTimestamp($processedAt),
        ]);
    }

    /** @param array<string,mixed>|null $snapshot */
    private function priorSnapshotForSequence(
        ?array $snapshot,
        int $currentSequence,
        DateTimeImmutable $coverageStart,
        DateTimeImmutable $verifiedThrough,
    ): ?array {
        if ($snapshot === null) {
            return null;
        }
        $priorSequence = filter_var(
            $snapshot['verified_through_sequence_no'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $priorBatch = trim((string) ($snapshot['verified_through_batch_id'] ?? ''));
        $priorPayload = $this->hash64(
            $snapshot['verified_through_outbox_payload_sha256'] ?? null,
        );
        if ($priorSequence === false || $priorSequence <= 0 || $priorBatch === '') {
            throw new LogicException('SESSION_EVIDENCE_NOT_CONTIGUOUS_WITH_OUTBOX');
        }
        if ($priorSequence !== $currentSequence - 1) {
            // With no ACTIVE lifecycle, the retained feed is no longer needed
            // for horizon accounting and a fresh contiguous window may start.
            if ($this->sameInstant($coverageStart, $verifiedThrough)) {
                return null;
            }
            throw new LogicException('SESSION_EVIDENCE_NOT_CONTIGUOUS_WITH_OUTBOX');
        }
        $exists = DB::table('entry_signal_shadow_processed_batches')
            ->where('sequence_no', $priorSequence)
            ->where('batch_id', $priorBatch)
            ->where('payload_sha256', $priorPayload)
            ->exists();
        if (! $exists) {
            throw new LogicException('SESSION_EVIDENCE_PRIOR_OUTBOX_MISSING');
        }

        return $snapshot;
    }

    /** @param array<string,mixed> $prediction @return list<int|float|string> */
    private function predictionPriority(array $prediction): array
    {
        $expectedReturn = $prediction['expected_return'] ?? null;
        if (! is_numeric($expectedReturn) || ! is_finite((float) $expectedReturn)) {
            throw new LogicException('ENTRY_SHADOW_EXPECTED_RETURN_INVALID');
        }
        $quality = match (strtolower(trim((string) (
            $prediction['model_quality_label'] ?? ''
        )))) {
            'top+' => 1,
            'top', 'quality', 'strong' => 2,
            'solid' => 3,
            'basic', 'test' => 4,
            'underperform', 'unqualified', 'not qualified' => 5,
            default => throw new LogicException('ENTRY_SHADOW_MODEL_QUALITY_INVALID'),
        };

        return [
            $this->databaseBool($prediction['quality_gate_passed'] ?? null) ? 0 : 1,
            $quality,
            $this->databaseBool($prediction['selected_for_prediction'] ?? null) ? 0 : 1,
            $this->positiveInt($prediction, 'horizon'),
            -((float) $expectedReturn),
            $this->requiredString($prediction, 'variant'),
            $this->positiveInt($prediction, 'id'),
        ];
    }

    /**
     * Any ACTIVE lifecycle whose instrument disappeared from the new complete
     * scope manifest is hidden immediately. It may not borrow a heartbeat from
     * an older sequence.
     *
     * @param  list<int>  $sourceInstrumentIds
     */
    private function staleFeedsMissingFromBatch(
        array $sourceInstrumentIds,
        DateTimeImmutable $observedAt,
    ): int {
        if ($sourceInstrumentIds === []) {
            throw new LogicException('ENTRY_SHADOW_STOCK_BATCH_EMPTY');
        }
        $feeds = DB::table('entry_signal_session_feed_states as feed')
            ->join(
                'entry_signal_lifecycles as lifecycle',
                'lifecycle.session_feed_state_id',
                '=',
                'feed.id',
            )
            ->where('lifecycle.status', 'ACTIVE')
            ->where('feed.provider_name', 'serving_prediction')
            ->where('feed.status', 'READY')
            ->whereNotIn('feed.source_instrument_id', $sourceInstrumentIds)
            ->orderBy('feed.id')
            ->get([
                'feed.id', 'feed.instrument_id', 'feed.resolver_version',
                'feed.source_instrument_id', 'feed.mapping_sha256',
                'feed.heartbeat_at', 'feed.heartbeat_ttl_seconds',
                'feed.last_verified_source_as_of',
                'feed.verification_payload_sha256', 'feed.verification_snapshot',
            ])
            ->unique('id');
        foreach ($feeds as $feed) {
            $this->recordStaleFeed($feed, $observedAt, 'SOURCE_SCOPE_MISSING');
        }

        return $feeds->count();
    }

    /** @param array<string,mixed> $batch */
    private function assertOutboxStillCurrent(array $batch): void
    {
        $rows = DB::connection('serving')
            ->table('serving_prediction_batch_outbox as outbox')
            ->join(
                'serving_prediction_batch_outbox_integrity as integrity',
                'integrity.sequence_no',
                '=',
                'outbox.sequence_no',
            )
            ->where('outbox.sequence_no', (int) $batch['sequence_no'])
            ->where('outbox.stream_key', self::SOURCE_STREAM_KEY)
            ->limit(2)
            ->get([
                'outbox.batch_id', 'outbox.header_sha256',
                'outbox.prediction_manifest_sha256',
                'outbox.expected_scope_manifest_sha256',
                'outbox.release_manifest_sha256', 'outbox.request_sha256',
                'outbox.payload_sha256', 'integrity.prediction_count_valid',
                'integrity.scope_count_valid', 'integrity.prediction_hash_valid',
                'integrity.scope_hash_valid', 'integrity.release_hash_valid',
                'integrity.payload_hash_valid',
            ]);
        if ($rows->count() !== 1) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_CHANGED_DURING_EVALUATION');
        }
        $current = (array) $rows->first();
        if ((string) ($current['batch_id'] ?? '') !== (string) $batch['batch_id']) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_CHANGED_DURING_EVALUATION');
        }
        foreach ([
            'header_sha256', 'prediction_manifest_sha256',
            'expected_scope_manifest_sha256', 'release_manifest_sha256',
            'request_sha256', 'payload_sha256',
        ] as $hash) {
            if (! hash_equals(
                $this->hash64($batch[$hash] ?? null),
                $this->hash64($current[$hash] ?? null),
            )) {
                throw new LogicException('ENTRY_SHADOW_OUTBOX_CHANGED_DURING_EVALUATION');
            }
        }
        foreach ([
            'prediction_count_valid', 'scope_count_valid',
            'prediction_hash_valid', 'scope_hash_valid',
            'release_hash_valid', 'payload_hash_valid',
        ] as $flag) {
            if (! $this->databaseBool($current[$flag] ?? null)) {
                throw new LogicException('ENTRY_SHADOW_OUTBOX_CHANGED_DURING_EVALUATION');
            }
        }
    }

    private function scopeTuple(
        int $instrumentId,
        string $releaseId,
        int $horizon,
        string $variant,
    ): string {
        return implode(chr(0), [
            (string) $instrumentId,
            $releaseId,
            (string) $horizon,
            $variant,
        ]);
    }

    /** @param array<string,mixed> $runtime */
    private function coverageStartForInstrument(
        int $instrumentId,
        DateTimeImmutable $currentAsOf,
        array $runtime,
    ): DateTimeImmutable {
        $oldestActive = DB::table('entry_signal_lifecycles as lifecycle')
            ->join(
                'entry_signal_decisions as decision',
                'decision.id',
                '=',
                'lifecycle.entry_signal_decision_id',
            )
            ->where('lifecycle.instrument_id', $instrumentId)
            ->where('lifecycle.status', 'ACTIVE')
            ->min('decision.source_as_of');
        $coverageStart = $oldestActive === null
            ? $currentAsOf
            : $this->canonicalizer->dateTime($oldestActive);
        $cutover = $this->canonicalizer->dateTime($runtime['cutover_at'] ?? null);
        if ($coverageStart < $cutover || $coverageStart > $currentAsOf) {
            throw new LogicException('SESSION_EVIDENCE_COVERAGE_WINDOW_INVALID');
        }

        return $coverageStart;
    }

    /** @param array<string,mixed> $snapshot */
    private function materializeSessionEvents(
        array $snapshot,
        DateTimeImmutable $materializedAt,
    ): void {
        $sessions = $snapshot['sessions'] ?? null;
        if (! is_array($sessions)) {
            throw new LogicException('SESSION_EVIDENCE_LEDGER_INVALID');
        }
        foreach ($sessions as $session) {
            if (! is_array($session) || array_is_list($session)) {
                throw new LogicException('SESSION_EVIDENCE_LEDGER_INVALID');
            }
            $json = $this->canonicalizer->json($session);
            DB::statement(<<<'SQL'
                WITH payload AS (
                    SELECT CAST(? AS jsonb) AS evidence
                )
                INSERT INTO entry_signal_shadow_session_events (
                    source_event_key, instrument_id, source_instrument_id,
                    source_sequence_no, batch_id, outbox_payload_sha256,
                    market_date, source_as_of, mapping_sha256,
                    scope_manifest_sha256,
                    payload_sha256, evidence_sha256, evidence, materialized_at
                )
                SELECT CAST(? AS char(64)), CAST(? AS bigint), CAST(? AS bigint),
                       CAST(? AS bigint), CAST(? AS uuid), CAST(? AS char(64)),
                       CAST(? AS date), CAST(? AS timestamptz), CAST(? AS char(64)),
                       CAST(? AS char(64)), CAST(? AS char(64)),
                       CAST(encode(sha256(convert_to(payload.evidence::text, 'UTF8')), 'hex') AS char(64)),
                       payload.evidence, CAST(? AS timestamptz)
                FROM payload
                ON CONFLICT (source_event_key) DO NOTHING
            SQL, [
                $json,
                (string) ($session['source_event_key'] ?? ''),
                (int) ($snapshot['local_instrument_id'] ?? 0),
                (int) ($snapshot['source_instrument_id'] ?? 0),
                (int) ($session['outbox_sequence_no'] ?? 0),
                (string) ($session['batch_id'] ?? ''),
                (string) ($session['outbox_payload_sha256'] ?? ''),
                (string) ($session['market_date'] ?? ''),
                (string) ($session['as_of'] ?? ''),
                (string) ($session['mapping_sha256'] ?? ''),
                (string) ($session['scope_manifest_sha256'] ?? ''),
                (string) ($session['payload_sha256'] ?? ''),
                $this->canonicalizer->databaseTimestamp($materializedAt),
            ]);

            $persisted = DB::table('entry_signal_shadow_session_events')
                ->where('source_event_key', (string) ($session['source_event_key'] ?? ''))
                ->where('instrument_id', (int) ($snapshot['local_instrument_id'] ?? 0))
                ->where('source_instrument_id', (int) ($snapshot['source_instrument_id'] ?? 0))
                ->where('source_sequence_no', (int) ($session['outbox_sequence_no'] ?? 0))
                ->where('batch_id', (string) ($session['batch_id'] ?? ''))
                ->where(
                    'outbox_payload_sha256',
                    (string) ($session['outbox_payload_sha256'] ?? ''),
                )
                ->where('mapping_sha256', (string) ($session['mapping_sha256'] ?? ''))
                ->where(
                    'scope_manifest_sha256',
                    (string) ($session['scope_manifest_sha256'] ?? ''),
                )
                ->where('payload_sha256', (string) ($session['payload_sha256'] ?? ''))
                ->whereRaw('evidence = CAST(? AS jsonb)', [$json])
                ->whereRaw(
                    "evidence_sha256 = encode(sha256(convert_to(evidence::text, 'UTF8')), 'hex')",
                )
                ->count();
            if ($persisted !== 1) {
                throw new LogicException('SESSION_EVIDENCE_LEDGER_CONFLICT');
            }
        }
    }

    /** @param array<string,mixed> $mapping @return array<string,mixed>|null */
    private function priorFeedSnapshot(array $mapping): ?array
    {
        $rows = DB::table('entry_signal_session_feed_states')
            ->where('instrument_id', (int) $mapping['local_instrument_id'])
            ->where('provider_name', 'serving_prediction')
            ->limit(2)
            ->get([
                'resolver_version', 'source_instrument_id', 'mapping_sha256',
                'verification_snapshot',
            ]);
        if ($rows->isEmpty()) {
            return null;
        }
        if ($rows->count() !== 1) {
            throw new LogicException('SESSION_FEED_NOT_EXACTLY_ONE');
        }
        $row = $rows->first();
        if ((string) $row->resolver_version
                !== FinalEntryRawPredictionResolver::SESSION_RESOLVER_VERSION
            || (int) $row->source_instrument_id
                !== (int) $mapping['serving_instrument_id']
            || strtolower(trim((string) $row->mapping_sha256))
                !== strtolower((string) $mapping['sha256'])) {
            throw new LogicException('SESSION_FEED_IDENTITY_CHANGED');
        }
        if ($row->verification_snapshot === null) {
            return null;
        }
        $snapshot = $row->verification_snapshot;
        if (is_string($snapshot)) {
            try {
                $snapshot = json_decode($snapshot, true, 128, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
            }
        }
        if (! is_array($snapshot) || array_is_list($snapshot)) {
            throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
        }

        return $snapshot;
    }

    /**
     * Refreshes trust heartbeats only; cursor and decisions remain untouched.
     * A feed behind the global Outbox cursor or beyond the next weekday
     * publication window is made STALE instead of being kept alive forever.
     *
     * @return array{refreshed:int,staled:int}
     */
    private function refreshIdleFeeds(object $watermark): array
    {
        if ((int) ($watermark->last_sequence_no ?? 0) <= 0
            || $watermark->last_batch_id === null
            || $watermark->last_batch_completed_at === null) {
            return ['refreshed' => 0, 'staled' => 0];
        }
        $heartbeat = $this->databaseClock();
        $ttlSeconds = $this->heartbeatTtlSeconds();
        $refreshAfterSeconds = max(300, min(
            intdiv($ttlSeconds, 2),
            (int) config(
                'aktienki.final_entry_shadow.idle_refresh_after_seconds',
                21600,
            ),
        ));
        $refreshCutoff = $heartbeat->sub(
            new DateInterval('PT'.$refreshAfterSeconds.'S'),
        );
        $feeds = DB::table('entry_signal_session_feed_states as feed')
            ->join(
                'entry_signal_lifecycles as lifecycle',
                'lifecycle.session_feed_state_id',
                '=',
                'feed.id',
            )
            ->where('lifecycle.status', 'ACTIVE')
            ->where('feed.provider_name', 'serving_prediction')
            ->where('feed.status', 'READY')
            ->where(function (Builder $stale) use ($refreshCutoff): void {
                $stale->whereNull('feed.heartbeat_at')
                    ->orWhere(
                        'feed.heartbeat_at',
                        '<=',
                        $this->canonicalizer->databaseTimestamp($refreshCutoff),
                    );
            })
            ->orderBy('feed.id')
            ->get([
                'feed.id', 'feed.instrument_id', 'feed.resolver_version',
                'feed.source_instrument_id', 'feed.mapping_sha256',
                'feed.heartbeat_at', 'feed.heartbeat_ttl_seconds',
                'feed.last_verified_source_as_of',
                'feed.verification_payload_sha256', 'feed.verification_snapshot',
            ])
            ->unique('id')
            ->values();

        $refreshed = 0;
        $staled = 0;
        foreach ($feeds as $feed) {
            $mapping = [
                'local_instrument_id' => (int) $feed->instrument_id,
                'serving_instrument_id' => (int) $feed->source_instrument_id,
                'sha256' => strtolower(trim((string) $feed->mapping_sha256)),
            ];
            if ((string) $feed->resolver_version
                    !== FinalEntryRawPredictionResolver::SESSION_RESOLVER_VERSION
                || preg_match('/^[0-9a-f]{64}$/', $mapping['sha256']) !== 1) {
                throw new LogicException('SESSION_FEED_IDENTITY_CHANGED');
            }
            $snapshot = $this->decodeSnapshot($feed->verification_snapshot);
            if (! $this->snapshotMatchesWatermark($snapshot, $watermark)) {
                $this->recordStaleFeed(
                    $feed,
                    $heartbeat,
                    'SOURCE_OUTBOX_WATERMARK_BEHIND',
                );
                $staled++;

                continue;
            }
            $this->assertSnapshotMaterialized(
                $snapshot,
                $mapping,
                $watermark,
            );
            if (! $this->idleRefreshWithinPublicationWindow($snapshot, $heartbeat)) {
                $this->recordStaleFeed(
                    $feed,
                    $heartbeat,
                    'SOURCE_PUBLICATION_OVERDUE',
                );
                $staled++;

                continue;
            }
            $snapshot['heartbeat_at'] = $this->canonicalizer
                ->databaseTimestamp($heartbeat);
            $verifiedThrough = $this->canonicalizer->dateTime(
                $snapshot['verified_through_as_of'] ?? null,
            );
            $this->recordReadyFeed(
                $mapping,
                $snapshot,
                $heartbeat,
                $verifiedThrough,
            );
            $refreshed++;
        }

        return ['refreshed' => $refreshed, 'staled' => $staled];
    }

    /** @param array<string,mixed> $snapshot */
    private function snapshotMatchesWatermark(array $snapshot, object $watermark): bool
    {
        $sequence = filter_var(
            $snapshot['verified_through_sequence_no'] ?? null,
            FILTER_VALIDATE_INT,
        );

        return $sequence !== false
            && $sequence > 0
            && $sequence === (int) ($watermark->last_sequence_no ?? 0)
            && (string) ($snapshot['verified_through_batch_id'] ?? '')
                === (string) ($watermark->last_batch_id ?? '')
            && hash_equals(
                strtolower(trim((string) (
                    $snapshot['verified_through_outbox_payload_sha256'] ?? ''
                ))),
                strtolower(trim((string) ($watermark->last_payload_sha256 ?? ''))),
            );
    }

    /** @param array<string,mixed> $snapshot */
    private function idleRefreshWithinPublicationWindow(
        array $snapshot,
        DateTimeImmutable $heartbeat,
    ): bool {
        $timezoneName = trim((string) ($snapshot['session_timezone'] ?? ''));
        $sessions = $snapshot['sessions'] ?? null;
        if ($timezoneName === '' || ! is_array($sessions) || $sessions === []) {
            throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
        }
        try {
            $timezone = new DateTimeZone($timezoneName);
        } catch (\Throwable) {
            throw new LogicException('SESSION_TIMEZONE_INVALID');
        }
        $last = $sessions[array_key_last($sessions)] ?? null;
        if (! is_array($last) || array_is_list($last)) {
            throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
        }
        $marketDate = trim((string) ($last['market_date'] ?? ''));
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $marketDate, $timezone);
        if ($day === false || $day->format('Y-m-d') !== $marketDate) {
            throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
        }
        do {
            $day = $day->add(new DateInterval('P1D'));
        } while (in_array((int) $day->format('N'), [6, 7], true));
        $publicationDeadline = $day->setTime(23, 59, 59, 999999);

        return $heartbeat->setTimezone($timezone) <= $publicationDeadline;
    }

    /**
     * Verify the bounded feed snapshot against the immutable local ledger.
     * Serving may prune prediction rows after 90 days; heartbeat liveness must
     * not depend on that retention window.
     *
     * @param  array<string,mixed>  $snapshot
     * @param  array<string,mixed>  $mapping
     */
    private function assertSnapshotMaterialized(
        array $snapshot,
        array $mapping,
        object $watermark,
    ): void {
        if ((int) ($watermark->processed_batches ?? 0) <= 0
            || ! isset($snapshot['sessions'])
            || ! is_array($snapshot['sessions'])) {
            throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
        }
        foreach ($snapshot['sessions'] as $session) {
            if (! is_array($session) || array_is_list($session)) {
                throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
            }
            $json = $this->canonicalizer->json($session);
            $matches = DB::table('entry_signal_shadow_session_events as event')
                ->join(
                    'entry_signal_shadow_processed_batches as batch',
                    'batch.batch_id',
                    '=',
                    'event.batch_id',
                )
                ->where('event.source_event_key', (string) ($session['source_event_key'] ?? ''))
                ->where('event.instrument_id', (int) ($mapping['local_instrument_id'] ?? 0))
                ->where('event.source_instrument_id', (int) ($mapping['serving_instrument_id'] ?? 0))
                ->where(
                    'event.source_sequence_no',
                    (int) ($session['outbox_sequence_no'] ?? 0),
                )
                ->where(
                    'event.outbox_payload_sha256',
                    (string) ($session['outbox_payload_sha256'] ?? ''),
                )
                ->where('event.mapping_sha256', (string) ($mapping['sha256'] ?? ''))
                ->where(
                    'event.scope_manifest_sha256',
                    (string) ($session['scope_manifest_sha256'] ?? ''),
                )
                ->whereRaw('event.evidence = CAST(? AS jsonb)', [$json])
                ->whereRaw(
                    "event.evidence_sha256 = encode(sha256(convert_to(event.evidence::text, 'UTF8')), 'hex')",
                )
                ->count();
            if ($matches !== 1) {
                throw new LogicException('SESSION_EVIDENCE_LEDGER_CONFLICT');
            }
        }

        $throughBatchId = trim((string) (
            $snapshot['verified_through_batch_id'] ?? ''
        ));
        if ($throughBatchId === '') {
            throw new LogicException('SESSION_EVIDENCE_BATCH_WATERMARK_MISSING');
        }
        $throughSequence = filter_var(
            $snapshot['verified_through_sequence_no'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $throughPayload = $this->hash64(
            $snapshot['verified_through_outbox_payload_sha256'] ?? null,
        );
        if ($throughSequence === false || $throughSequence <= 0
            || ! $this->snapshotMatchesWatermark($snapshot, $watermark)) {
            throw new LogicException('SESSION_EVIDENCE_BEHIND_WORKER_WATERMARK');
        }
        $throughBatch = DB::table('entry_signal_shadow_processed_batches')
            ->where('sequence_no', $throughSequence)
            ->where('batch_id', $throughBatchId)
            ->where('payload_sha256', $throughPayload)
            ->first([
                'source_finished_at', 'expected_scope_manifest_sha256',
            ]);
        if ($throughBatch === null || ! $this->sameInstant(
            $snapshot['verified_through_batch_completed_at'] ?? null,
            $throughBatch->source_finished_at,
        ) || ! hash_equals(
            strtolower((string) $throughBatch->expected_scope_manifest_sha256),
            strtolower((string) (
                $snapshot['verified_through_scope_manifest_sha256'] ?? ''
            )),
        )) {
            throw new LogicException('SESSION_EVIDENCE_BATCH_WATERMARK_INVALID');
        }
    }

    /** @return array<string,mixed> */
    private function decodeSnapshot(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 128, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
            }
        }
        if (! is_array($value) || array_is_list($value)) {
            throw new LogicException('PRIOR_SESSION_EVIDENCE_INVALID');
        }

        return $value;
    }

    private function recordStaleFeed(
        object $feed,
        DateTimeImmutable $observedAt,
        string $reason,
    ): int {
        $snapshot = $this->decodeSnapshot($feed->verification_snapshot ?? null);
        $heartbeat = $this->canonicalizer->dateTime($feed->heartbeat_at ?? null);
        if ($heartbeat > $observedAt->add(new DateInterval('PT5M'))
            || preg_match('/^[A-Z][A-Z0-9_]*$/', $reason) !== 1) {
            throw new LogicException('SESSION_FEED_STALE_TRANSITION_INVALID');
        }
        $row = DB::selectOne(<<<'SQL'
            SELECT record_entry_signal_session_feed_state(
                CAST(? AS bigint), CAST('serving_prediction' AS varchar),
                CAST(? AS varchar), CAST(? AS bigint), CAST(? AS char(64)),
                CAST('STALE' AS varchar), CAST(? AS jsonb),
                CAST(? AS timestamptz), CAST(? AS integer),
                CAST(? AS timestamptz), CAST(? AS char(64)), CAST(? AS jsonb)
            ) AS id
        SQL, [
            (int) $feed->instrument_id,
            (string) $feed->resolver_version,
            (int) $feed->source_instrument_id,
            strtolower(trim((string) $feed->mapping_sha256)),
            $this->canonicalizer->json([$reason]),
            $this->canonicalizer->databaseTimestamp($heartbeat),
            (int) $feed->heartbeat_ttl_seconds,
            (string) $feed->last_verified_source_as_of,
            strtolower(trim((string) $feed->verification_payload_sha256)),
            $this->canonicalizer->json($snapshot),
        ]);
        $id = (int) ($row->id ?? 0);
        if ($id !== (int) $feed->id) {
            throw new LogicException('SESSION_FEED_STALE_WRITE_FAILED');
        }

        return $id;
    }

    /**
     * @param  array<string,mixed>  $mapping
     * @param  array<string,mixed>  $snapshot
     */
    private function recordReadyFeed(
        array $mapping,
        array $snapshot,
        DateTimeImmutable $heartbeat,
        DateTimeImmutable $verifiedThrough,
    ): int {
        $row = DB::selectOne(<<<'SQL'
            WITH payload AS (
                SELECT CAST(? AS jsonb) AS snapshot
            )
            SELECT record_entry_signal_session_feed_state(
                CAST(? AS bigint),
                CAST('serving_prediction' AS varchar),
                CAST(? AS varchar),
                CAST(? AS bigint),
                CAST(? AS char(64)),
                CAST('READY' AS varchar),
                '[]'::jsonb,
                CAST(? AS timestamptz),
                CAST(? AS integer),
                CAST(? AS timestamptz),
                CAST(encode(sha256(convert_to(payload.snapshot::text, 'UTF8')), 'hex') AS char(64)),
                payload.snapshot
            ) AS id
            FROM payload
        SQL, [
            $this->canonicalizer->json($snapshot),
            (int) $mapping['local_instrument_id'],
            FinalEntryRawPredictionResolver::SESSION_RESOLVER_VERSION,
            (int) $mapping['serving_instrument_id'],
            (string) $mapping['sha256'],
            $this->canonicalizer->databaseTimestamp($heartbeat),
            $this->heartbeatTtlSeconds(),
            $this->canonicalizer->databaseTimestamp($verifiedThrough),
        ]);
        $id = (int) ($row->id ?? 0);
        if ($id <= 0) {
            throw new LogicException('SESSION_FEED_WRITE_FAILED');
        }

        return $id;
    }

    private function heartbeatTtlSeconds(): int
    {
        return max(600, min(86400, (int) config(
            'aktienki.final_entry_shadow.heartbeat_ttl_seconds',
            86400,
        )));
    }

    /** @return array{advanced:int,terminal:int} */
    private function advanceActiveLifecycles(
        int $localInstrumentId,
        DateTimeImmutable $observedAt,
    ): array {
        $lifecycles = DB::table('entry_signal_lifecycles')
            ->where('instrument_id', $localInstrumentId)
            ->where('status', 'ACTIVE')
            ->orderBy('user_id')
            ->orderBy('context_key')
            ->get(['user_id', 'context_key']);
        $terminal = 0;
        foreach ($lifecycles as $lifecycle) {
            $row = DB::selectOne(<<<'SQL'
                SELECT advance_entry_signal_lifecycle(
                    CAST(? AS bigint), CAST(? AS varchar), CAST(? AS bigint),
                    CAST(? AS timestamptz), NULL::jsonb, NULL::jsonb
                ) AS lifecycle_id
            SQL, [
                (int) $lifecycle->user_id,
                (string) $lifecycle->context_key,
                $localInstrumentId,
                $this->canonicalizer->databaseTimestamp($observedAt),
            ]);
            if (($row->lifecycle_id ?? null) !== null) {
                $terminal++;
            }
        }

        return ['advanced' => $lifecycles->count(), 'terminal' => $terminal];
    }

    /** @param list<array<string,mixed>> $rows */
    private function maximumAsOf(array $rows): DateTimeImmutable
    {
        $maximum = null;
        foreach ($rows as $row) {
            $candidate = $this->canonicalizer->dateTime($row['as_of'] ?? null);
            if ($maximum === null || $candidate > $maximum) {
                $maximum = $candidate;
            }
        }
        if ($maximum === null) {
            throw new LogicException('ENTRY_SHADOW_SOURCE_AS_OF_MISSING');
        }

        return $maximum;
    }

    private function databaseClock(): DateTimeImmutable
    {
        $row = DB::selectOne('SELECT clock_timestamp() AS observed_at');
        if ($row === null || ! isset($row->observed_at)) {
            throw new LogicException('SERVER_CLOCK_UNAVAILABLE');
        }

        return $this->canonicalizer->dateTime((string) $row->observed_at);
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

    /** @return array<string,mixed> */
    private function jsonObject(mixed $value, bool $allowEmpty = false): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 256, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new LogicException('ENTRY_SHADOW_OUTBOX_JSON_INVALID');
            }
        }
        if (! is_array($value)
            || (array_is_list($value) && ! ($allowEmpty && $value === []))) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_JSON_INVALID');
        }

        return $value;
    }

    private function hash64(mixed $value): string
    {
        $hash = strtolower(trim((string) $value));
        if (preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
            throw new LogicException('ENTRY_SHADOW_OUTBOX_HASH_INVALID');
        }

        return $hash;
    }

    private function databaseBool(mixed $value): bool
    {
        return match (true) {
            $value === true, $value === 1, $value === '1', $value === 't' => true,
            $value === false, $value === 0, $value === '0', $value === 'f' => false,
            default => throw new LogicException('AUTHORITATIVE_BOOLEAN_INVALID'),
        };
    }

    private function safeCode(string $candidate, string $fallback): string
    {
        return preg_match('/^[A-Z][A-Z0-9_]*$/', $candidate) === 1
            ? $candidate
            : $fallback;
    }
}
