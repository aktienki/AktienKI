<?php

namespace App\Services;

use App\Data\FinalEntryRawPredictionResolution;
use App\Data\FinalEntrySignalDecisionInput;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Read-only trust boundary for one exact serving prediction.
 *
 * The numeric prediction id is only a locator/diagnostic. Semantic identity is
 * derived from the verified serving tuple by FinalEntryCanonicalizer.
 */
class FinalEntryRawPredictionResolver
{
    public const SESSION_RESOLVER_VERSION = 'trusted-session-evidence-v1';

    /** @var array<int,array<string,mixed>> */
    private array $mappingCache = [];

    /** @var array<string,FinalEntryRawPredictionResolution> */
    private array $resolutionCache = [];

    /** @var array<string,array<string,mixed>> */
    private array $batchRatingCache = [];

    /** @var array<int,array<string,mixed>> */
    private array $outboxPredictionCache = [];

    public function __construct(
        private readonly FinalEntryCanonicalizer $canonicalizer,
    ) {}

    public function resetRuntimeCache(): void
    {
        $this->mappingCache = [];
        $this->resolutionCache = [];
        $this->batchRatingCache = [];
        $this->outboxPredictionCache = [];
    }

    /**
     * Prime one immutable terminal-Outbox batch. The decision hot path then
     * never re-reads mutable serving prediction/release tables.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    public function primeOutboxBatch(array $rows): void
    {
        if ($rows === []) {
            throw new LogicException('SOURCE_OUTBOX_BATCH_EMPTY');
        }
        $groups = [];
        foreach ($rows as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new LogicException('SOURCE_OUTBOX_EVENT_INVALID');
            }
            $id = $this->positiveInt($row, 'id');
            $batchId = $this->requiredString($row, 'batch_id');
            $instrumentId = $this->positiveInt($row, 'instrument_id');
            if (isset($this->outboxPredictionCache[$id])) {
                throw new LogicException('SOURCE_OUTBOX_EVENT_DUPLICATE');
            }
            $groups[$batchId.':'.$instrumentId][] = $row;
        }

        foreach ($groups as $cacheKey => $group) {
            $hasBuy = count(array_filter(
                $group,
                static fn (array $row): bool => strtoupper(trim((string) (
                    $row['signal'] ?? ''
                ))) === 'BUY',
            )) > 0;
            $rating = $hasBuy ? $this->deriveImmutableBatchRating($group) : [];
            if ($hasBuy) {
                $this->batchRatingCache[$cacheKey] = $rating;
            }
            foreach ($group as $row) {
                $id = $this->positiveInt($row, 'id');
                $this->outboxPredictionCache[$id] = strtoupper(trim((string) (
                    $row['signal'] ?? ''
                ))) === 'BUY' ? array_merge($row, $rating) : $row;
            }
        }
    }

    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    public function resolveOutboxInstrumentMapping(array $snapshot): array
    {
        $sourceInstrumentId = $this->positiveInt($snapshot, 'instrument_id');
        if (isset($this->mappingCache[$sourceInstrumentId])) {
            return $this->mappingCache[$sourceInstrumentId];
        }
        if (($snapshot['instrument_type'] ?? null) !== 'stock') {
            throw new LogicException('SOURCE_INSTRUMENT_NOT_STOCK');
        }

        $localInstrumentId = null;
        $isin = $this->normalizeIdentity($snapshot['isin'] ?? null);
        if ($isin !== '') {
            $localIds = DB::table('instruments')
                ->whereRaw(
                    "UPPER(REGEXP_REPLACE(COALESCE(isin, ''), '[^A-Za-z0-9]', '', 'g')) = ?",
                    [$isin],
                )
                ->orderBy('id')
                ->limit(2)
                ->pluck('id');
            if ($localIds->count() === 1) {
                $localInstrumentId = (int) $localIds->first();
            }
        }
        if ($localInstrumentId === null) {
            $provider = strtoupper(trim((string) ($snapshot['provider_symbol'] ?? '')));
            $exchange = strtoupper(trim((string) ($snapshot['exchange'] ?? '')));
            if ($provider === '' || $exchange === '') {
                throw new LogicException('INSTRUMENT_MAPPING_NOT_UNIQUE');
            }
            $localIds = DB::table('instruments as instrument')
                ->join('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
                ->whereRaw('UPPER(instrument.provider_symbol) = ?', [$provider])
                ->where(function ($query) use ($exchange): void {
                    $query->whereRaw('UPPER(exchange.code) = ?', [$exchange])
                        ->orWhereRaw('UPPER(exchange.mic) = ?', [$exchange]);
                })
                ->orderBy('instrument.id')
                ->limit(2)
                ->pluck('instrument.id');
            if ($localIds->count() !== 1) {
                throw new LogicException('INSTRUMENT_MAPPING_NOT_UNIQUE');
            }
            $localInstrumentId = (int) $localIds->first();
        }

        return $this->mappingCache[$sourceInstrumentId] = $this->resolveMapping(
            $localInstrumentId,
            $snapshot,
            true,
        );
    }

    /**
     * Resolve the only main-database instrument that can be bound to one
     * serving instrument. The shadow worker uses this before it creates the
     * authoritative session-feed row that resolve() subsequently verifies.
     *
     * @return array<string, mixed>
     */
    public function resolveServingInstrumentMapping(int $sourceInstrumentId): array
    {
        if ($sourceInstrumentId <= 0) {
            throw new LogicException('SOURCE_INSTRUMENT_ID_INVALID');
        }
        if (isset($this->mappingCache[$sourceInstrumentId])) {
            return $this->mappingCache[$sourceInstrumentId];
        }

        $sources = DB::connection('serving')
            ->table('serving_instruments')
            ->where('id', $sourceInstrumentId)
            ->where('instrument_type', 'stock')
            ->limit(2)
            ->get([
                'id as instrument_id', 'symbol', 'provider_symbol', 'isin', 'exchange',
                'country_code', 'sector_code', 'instrument_type',
            ]);
        if ($sources->count() !== 1) {
            throw new LogicException('SOURCE_INSTRUMENT_NOT_EXACTLY_ONE');
        }
        $source = (array) $sources->first();

        $localInstrumentId = null;
        $isin = $this->normalizeIdentity($source['isin'] ?? null);
        if ($isin !== '') {
            $localIds = DB::table('instruments')
                ->whereRaw(
                    "UPPER(REGEXP_REPLACE(COALESCE(isin, ''), '[^A-Za-z0-9]', '', 'g')) = ?",
                    [$isin],
                )
                ->orderBy('id')
                ->limit(2)
                ->pluck('id');
            $servingCount = DB::connection('serving')
                ->table('serving_instruments')
                ->whereRaw(
                    "UPPER(REGEXP_REPLACE(COALESCE(isin, ''), '[^A-Za-z0-9]', '', 'g')) = ?",
                    [$isin],
                )
                ->count();
            if ($localIds->count() === 1 && $servingCount === 1) {
                $localInstrumentId = (int) $localIds->first();
            }
        }

        if ($localInstrumentId === null) {
            $provider = strtoupper(trim((string) ($source['provider_symbol'] ?? '')));
            $exchange = strtoupper(trim((string) ($source['exchange'] ?? '')));
            if ($provider === '' || $exchange === '') {
                throw new LogicException('INSTRUMENT_MAPPING_NOT_UNIQUE');
            }
            $servingCount = DB::connection('serving')
                ->table('serving_instruments')
                ->whereRaw('UPPER(provider_symbol) = ?', [$provider])
                ->whereRaw('UPPER(exchange) = ?', [$exchange])
                ->count();
            $localIds = DB::table('instruments as instrument')
                ->join('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
                ->whereRaw('UPPER(instrument.provider_symbol) = ?', [$provider])
                ->where(function ($query) use ($exchange): void {
                    $query->whereRaw('UPPER(exchange.code) = ?', [$exchange])
                        ->orWhereRaw('UPPER(exchange.mic) = ?', [$exchange]);
                })
                ->orderBy('instrument.id')
                ->limit(2)
                ->pluck('instrument.id');
            if ($servingCount !== 1 || $localIds->count() !== 1) {
                throw new LogicException('INSTRUMENT_MAPPING_NOT_UNIQUE');
            }
            $localInstrumentId = (int) $localIds->first();
        }

        return $this->mappingCache[$sourceInstrumentId] = $this->resolveMapping(
            $localInstrumentId,
            $source,
        );
    }

    public function resolve(
        FinalEntrySignalDecisionInput $input,
    ): FinalEntryRawPredictionResolution {
        if ($input->instrumentId <= 0 || $input->sourcePredictionId <= 0) {
            throw new LogicException('SOURCE_LOCATOR_INVALID');
        }
        $cacheKey = $input->instrumentId.':'.$input->sourcePredictionId;
        if (isset($this->resolutionCache[$cacheKey])) {
            return $this->resolutionCache[$cacheKey];
        }

        $clock = DB::selectOne('SELECT clock_timestamp() AS evaluated_at');
        if ($clock === null || ! isset($clock->evaluated_at)) {
            throw new LogicException('SERVER_CLOCK_UNAVAILABLE');
        }
        $evaluatedAt = $this->canonicalizer->dateTime((string) $clock->evaluated_at);

        $runtimeRows = DB::table('entry_signal_runtime_config')
            ->where('singleton', true)
            ->limit(2)
            ->get(['singleton', 'evaluator_version', 'cutover_at']);
        if ($runtimeRows->count() !== 1) {
            throw new LogicException('ENTRY_RUNTIME_CONFIG_INVALID');
        }
        $runtime = (array) $runtimeRows->first();

        $row = $this->outboxPredictionCache[$input->sourcePredictionId] ?? null;
        if ($row === null) {
            throw new LogicException('SOURCE_OUTBOX_EVENT_NOT_PRIMED');
        }
        $sourceInstrumentId = $this->positiveInt($row, 'instrument_id');
        $mapping = $this->mappingCache[$sourceInstrumentId] ?? null;
        if ($mapping === null
            || (int) ($mapping['local_instrument_id'] ?? 0) !== $input->instrumentId) {
            throw new LogicException('SOURCE_OUTBOX_MAPPING_NOT_PRIMED');
        }

        $feed = [];
        if (strtoupper(trim((string) ($row['signal'] ?? ''))) === 'BUY') {
            $feedRows = DB::table('entry_signal_session_feed_states')
                ->where('instrument_id', $input->instrumentId)
                ->where('provider_name', 'serving_prediction')
                ->limit(2)
                ->get([
                    'id', 'instrument_id', 'provider_name', 'resolver_version',
                    'source_instrument_id', 'mapping_sha256', 'status', 'reason_codes',
                    'heartbeat_at', 'heartbeat_ttl_seconds',
                    'last_verified_source_as_of', 'verification_payload_sha256',
                    'verification_snapshot',
                    DB::raw("verification_payload_sha256 = encode(sha256(convert_to(verification_snapshot::text, 'UTF8')), 'hex') AS verification_hash_valid"),
                ]);
            if ($feedRows->count() !== 1) {
                throw new LogicException('SESSION_FEED_NOT_EXACTLY_ONE');
            }
            $feed = (array) $feedRows->first();
        }

        return $this->resolutionCache[$cacheKey] = $this->verifyAuthoritativeSnapshot(
            row: $row,
            mapping: $mapping,
            feed: $feed,
            runtime: $runtime,
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * Kept protected so the runtime harness can exercise all fail-closed
     * validation without creating or mutating production tables.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $feed
     * @param  array<string, mixed>  $runtime
     */
    protected function verifyAuthoritativeSnapshot(
        array $row,
        array $mapping,
        array $feed,
        array $runtime,
        DateTimeImmutable $evaluatedAt,
    ): FinalEntryRawPredictionResolution {
        if (($runtime['evaluator_version'] ?? null)
                !== FinalEntrySignalDecisionService::EVALUATOR_VERSION) {
            throw new LogicException('ENTRY_RUNTIME_VERSION_MISMATCH');
        }
        $cutoverAt = $this->timestamp($runtime, 'cutover_at');

        if (($row['batch_status'] ?? null) !== 'complete') {
            throw new LogicException('SOURCE_BATCH_NOT_COMPLETE');
        }
        // selected_for_prediction is frozen ranking evidence, never an
        // eligibility gate. Every enabled/eligible non-underperform model may
        // produce a FINAL BUY.
        $this->databaseBool($row['selected_for_prediction'] ?? null);
        if (! $this->databaseBool($row['prediction_enabled'] ?? null)
            || ($row['prediction_status'] ?? null) !== 'eligible'
            || ($row['instrument_type'] ?? null) !== 'stock') {
            throw new LogicException('SOURCE_SCOPE_NOT_ELIGIBLE');
        }

        $sourceInstrumentId = $this->positiveInt($row, 'instrument_id');
        $horizon = $this->positiveInt($row, 'horizon');
        $qualityHorizons = $this->qualityHorizons($row['quality_horizons'] ?? null);
        if (! in_array($horizon, $qualityHorizons, true)) {
            throw new LogicException('SOURCE_QUALITY_HORIZONS_INVALID');
        }
        $sourcePredictionId = $this->positiveInt($row, 'id');
        $batchId = $this->requiredString($row, 'batch_id');
        $releaseId = $this->requiredString($row, 'release_id');
        $variant = $this->requiredString($row, 'variant');
        $releaseContentHash = strtolower(trim((string) (
            $row['release_content_sha256'] ?? ''
        )));
        if (! $this->databaseBool($row['source_release_bound'] ?? null)
            || (string) ($row['joined_release_id'] ?? '') !== $releaseId
            || (int) ($row['release_instrument_id'] ?? 0) !== $sourceInstrumentId
            || preg_match('/^[0-9a-f]{64}$/', $releaseContentHash) !== 1) {
            throw new LogicException('SOURCE_RELEASE_NOT_BATCH_BOUND');
        }
        $outboxSequence = $this->positiveInt($row, 'outbox_sequence_no');
        $outboxPayloadHash = strtolower(trim((string) (
            $row['outbox_payload_sha256'] ?? ''
        )));
        $scopeManifestHash = strtolower(trim((string) (
            $row['scope_manifest_sha256'] ?? ''
        )));
        if (($row['outbox_contract_version'] ?? null)
                !== 'serving-prediction-terminal-v1'
            || ($row['outbox_stream_key'] ?? null) !== 'stock-final-entry'
            || preg_match('/^[0-9a-f]{64}$/', $outboxPayloadHash) !== 1
            || preg_match('/^[0-9a-f]{64}$/', $scopeManifestHash) !== 1) {
            throw new LogicException('SOURCE_OUTBOX_BINDING_INVALID');
        }

        $sourceAsOf = $this->timestamp($row, 'as_of');
        $batchCompletedAt = $this->timestamp($row, 'batch_completed_at');
        $createdAt = $this->timestamp($row, 'created_at');
        if ($sourceAsOf < $cutoverAt
            || $batchCompletedAt < $cutoverAt
            || $sourceAsOf > $batchCompletedAt
            || $batchCompletedAt > $evaluatedAt
            || $createdAt > $evaluatedAt->add(new DateInterval('PT5M'))) {
            throw new LogicException('SOURCE_EVENT_OUTSIDE_CUTOVER_OR_TIME_ORDER');
        }

        $mappingHash = strtolower((string) ($mapping['sha256'] ?? ''));
        $mappingForHash = $mapping;
        unset($mappingForHash['sha256']);
        if (preg_match('/^[0-9a-f]{64}$/', $mappingHash) !== 1
            || ! hash_equals($mappingHash, $this->canonicalizer->sha256($mappingForHash))
            || (int) ($mapping['serving_instrument_id'] ?? 0) !== $sourceInstrumentId) {
            throw new LogicException('INSTRUMENT_MAPPING_INVALID');
        }
        $localInstrumentId = $this->positiveInt($mapping, 'local_instrument_id');
        $timezone = $this->requiredString($mapping, 'session_timezone');
        try {
            new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new LogicException('SESSION_TIMEZONE_INVALID');
        }

        $qualityClass = strtolower(trim((string) ($row['model_quality_class'] ?? '')));
        $qualityGate = $this->databaseBool($row['quality_gate_passed'] ?? null);
        $rawSignal = strtoupper(trim((string) ($row['signal'] ?? '')));
        $batchRating = null;
        $batchUnderlyingRating = null;
        $batchIsWatch = false;
        $batchRatingEvidence = [];
        $batchRatingEvidenceHash = null;
        if ($rawSignal === 'BUY') {
            $batchRating = trim((string) ($row['batch_buy_rating'] ?? ''));
            $batchUnderlyingRating = trim((string) (
                $row['batch_underlying_buy_rating'] ?? ''
            ));
            $batchIsWatch = $this->databaseBool($row['batch_is_watch'] ?? null);
            $batchRatingEvidence = $this->jsonValue($row['batch_rating_evidence'] ?? null);
            $batchRatingEvidenceHash = strtolower(trim((string) (
                $row['batch_rating_evidence_sha256'] ?? ''
            )));
            if ($this->servingRatingScore10($batchRating) === null
                || $this->servingRatingScore10($batchUnderlyingRating) === null
                || ($batchIsWatch && $batchRating !== '2')
                || (! $batchIsWatch && $batchRating !== $batchUnderlyingRating)
                || ! is_array($batchRatingEvidence)
                || ! array_is_list($batchRatingEvidence)
                || $batchRatingEvidence === []
                || preg_match('/^[0-9a-f]{64}$/', $batchRatingEvidenceHash) !== 1
                || ! hash_equals(
                    $batchRatingEvidenceHash,
                    $this->canonicalizer->sha256($batchRatingEvidence),
                )) {
                throw new LogicException('SOURCE_BATCH_RATING_INVALID');
            }
        }
        $sessionFeed = [];
        if ($rawSignal === 'BUY') {
            $this->assertAuthoritativeFeed(
                $feed,
                $localInstrumentId,
                $sourceInstrumentId,
                $mappingHash,
                $sourceAsOf,
                $evaluatedAt,
                $timezone,
                $horizon,
            );
            $sessionFeed = $this->sessionFeedEvidence($feed);
        }
        $source = [
            'source_system' => 'serving',
            'id' => $sourcePredictionId,
            'batch_id' => $batchId,
            'instrument_id' => $sourceInstrumentId,
            'release_id' => $releaseId,
            'release_content_sha256' => $releaseContentHash,
            'outbox_sequence_no' => $outboxSequence,
            'outbox_contract_version' => 'serving-prediction-terminal-v1',
            'outbox_stream_key' => 'stock-final-entry',
            'outbox_payload_sha256' => $outboxPayloadHash,
            'scope_manifest_sha256' => $scopeManifestHash,
            'as_of' => $this->canonicalizer->databaseTimestamp($sourceAsOf),
            'batch_completed_at' => $this->canonicalizer->databaseTimestamp($batchCompletedAt),
            'market_date' => $sourceAsOf->setTimezone(new DateTimeZone($timezone))->format('Y-m-d'),
            'horizon' => $horizon,
            'variant' => $variant,
            'signal' => $rawSignal,
            'expected_return' => $row['expected_return'] ?? null,
            'target_price' => $row['target_price'] ?? null,
            'calibrated_score' => $row['calibrated_score'] ?? null,
            'risk_score' => $row['risk_score'] ?? null,
            'confidence' => $row['confidence'] ?? null,
            'compact_context' => $this->jsonValue($row['compact_context'] ?? null),
            'created_at' => $this->canonicalizer->databaseTimestamp($createdAt),
            'batch_status' => 'complete',
            'batch_calculation_date' => $row['batch_calculation_date'] ?? null,
            'pipeline_version' => $row['pipeline_version'] ?? null,
            'scope_active' => true,
            'release_active' => true,
            'model_quality_class' => $qualityClass,
            'model_quality_label' => $row['model_quality_label'] ?? null,
            'quality_gate_passed' => $qualityGate,
            'quality_horizons' => $qualityHorizons,
            'batch_buy_rating' => $batchRating,
            'batch_underlying_buy_rating' => $batchUnderlyingRating,
            'batch_is_watch' => $batchIsWatch,
            'batch_rating_evidence_sha256' => $batchRatingEvidenceHash,
            'batch_rating_evidence' => $batchRatingEvidence,
            'entry_policy' => $this->jsonValue($row['entry_policy'] ?? null),
            'performance' => $this->jsonValue($row['performance'] ?? null),
            'symbol' => $row['symbol'] ?? null,
            'provider_symbol' => $row['provider_symbol'] ?? null,
            'isin' => $row['isin'] ?? null,
            'exchange' => $row['exchange'] ?? null,
            'country_code' => $row['country_code'] ?? null,
            'sector_code' => $row['sector_code'] ?? null,
            'instrument_type' => 'stock',
            'mapping' => $mapping,
        ];
        $eventKey = $this->canonicalizer->sourceEventKey($source);
        $payloadHash = $this->canonicalizer->sourcePayloadHash($source);

        return new FinalEntryRawPredictionResolution(
            source: $source,
            metrics: $this->metrics($source),
            mapping: $mapping,
            sessionFeed: $sessionFeed,
            cutoverAt: $cutoverAt,
            evaluatedAt: $evaluatedAt,
            sourceEventKey: $eventKey,
            sourcePayloadSha256: $payloadHash,
        );
    }

    /**
     * Mirrors the Serving stock rating rules over a caller-independent,
     * batch-bound prediction/release snapshot. Protected only for the
     * read-only runtime harness.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return array{batch_buy_rating:string,batch_underlying_buy_rating:string,batch_is_watch:bool,batch_rating_evidence_sha256:string,batch_rating_evidence:list<array<string,mixed>>}
     */
    protected function deriveImmutableBatchRating(array $rows): array
    {
        if ($rows === []) {
            throw new LogicException('SOURCE_BATCH_RATING_INVALID');
        }

        $evidence = [];
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new LogicException('SOURCE_BATCH_RATING_INVALID');
            }
            $releaseId = $this->requiredString($row, 'release_id');
            $horizon = $this->positiveInt($row, 'horizon');
            $variant = $this->requiredString($row, 'variant');
            $signal = strtoupper(trim((string) ($row['signal'] ?? '')));
            if (! in_array($signal, ['BUY', 'HOLD', 'WATCH', 'WAIT', 'SELL'], true)) {
                throw new LogicException('SOURCE_BATCH_RATING_INVALID');
            }
            $tuple = implode(chr(0), [$releaseId, (string) $horizon, $variant]);
            if (isset($seen[$tuple])) {
                throw new LogicException('SOURCE_BATCH_RATING_INVALID');
            }
            $seen[$tuple] = true;
            $scope = [
                'release_id' => $releaseId,
                'horizon' => $horizon,
                'variant' => $variant,
                'signal' => $signal,
                'expected_return' => $this->finiteMetric(
                    $row,
                    'expected_return',
                    NAN,
                ),
            ];
            if ($signal === 'BUY') {
                if (array_key_exists('model_quality_label', $row)
                    && array_key_exists('quality_gate_passed', $row)) {
                    $scope['quality'] = $this->normalizedQualityLabel(
                        $row['model_quality_label'],
                    );
                    $scope['quality_gate_passed'] = $this->databaseBool(
                        $row['quality_gate_passed'],
                    );
                } else {
                    // Compatibility for the pure deterministic unit harness;
                    // production rows are always frozen Outbox scopes.
                    $release = $this->jsonValue($row['compact_metrics'] ?? null);
                    $payload = is_array($release)
                        ? ($release['horizons'][(string) $horizon][$variant] ?? null)
                        : null;
                    $metrics = is_array($payload) ? ($payload['metrics'] ?? null) : null;
                    if (! is_array($payload) || array_is_list($payload)
                        || ! is_array($metrics) || array_is_list($metrics)) {
                        throw new LogicException('SOURCE_BATCH_RATING_INVALID');
                    }
                    $scope['quality'] = $this->batchQualityLabel($payload, $metrics);
                    $scope['quality_gate_passed'] = $this->batchQualityGate($payload, $metrics);
                }
            }
            $evidence[] = $scope;
        }
        $buyEvidence = array_values(array_filter(
            $evidence,
            static fn (array $scope): bool => $scope['signal'] === 'BUY',
        ));
        if ($buyEvidence === []) {
            throw new LogicException('SOURCE_BATCH_RATING_INVALID');
        }

        $confirmations = count($buyEvidence);
        $qualityGateCount = count(array_filter(
            $buyEvidence,
            static fn (array $scope): bool => $scope['quality_gate_passed'] === true,
        ));
        $hasAdditionalTop = false;
        foreach ($buyEvidence as $gateScope) {
            if (! $gateScope['quality_gate_passed']) {
                continue;
            }
            foreach ($buyEvidence as $topScope) {
                if (($topScope['horizon'] !== $gateScope['horizon']
                        || $topScope['variant'] !== $gateScope['variant'])
                    && in_array($topScope['quality'], ['Top', 'Top+'], true)) {
                    $hasAdditionalTop = true;
                    break 2;
                }
            }
        }
        $counts = array_count_values(array_column($buyEvidence, 'quality'));
        $underlyingRating = match (true) {
            $qualityGateCount >= 2 => '1++',
            $qualityGateCount === 1 && $hasAdditionalTop => '1+',
            $qualityGateCount === 1 => '1',
            ($counts['Top+'] ?? 0) > 0 => '2+',
            ($counts['Top'] ?? 0) >= 2 => '2+',
            ($counts['Top'] ?? 0) === 1 && $confirmations >= 2 => '2',
            ($counts['Top'] ?? 0) === 1 => '2-',
            ($counts['Solid'] ?? 0) >= 2 => '3+',
            ($counts['Solid'] ?? 0) === 1 && $confirmations >= 2 => '3',
            ($counts['Solid'] ?? 0) === 1 => '3-',
            ($counts['Basic'] ?? 0) >= 3 => '4+',
            ($counts['Basic'] ?? 0) === 2 => '4',
            ($counts['Basic'] ?? 0) === 1 => '4-',
            ($counts['Underperform'] ?? 0) >= 3 => '5+',
            ($counts['Underperform'] ?? 0) === 2 => '5',
            default => '5-',
        };
        $isWatch = false;
        foreach ($evidence as $shorter) {
            if ($shorter['expected_return'] >= 0) {
                continue;
            }
            foreach ($buyEvidence as $later) {
                if ($later['horizon'] > $shorter['horizon']) {
                    $isWatch = true;
                    break 2;
                }
            }
        }

        return [
            'batch_buy_rating' => $isWatch ? '2' : $underlyingRating,
            'batch_underlying_buy_rating' => $underlyingRating,
            'batch_is_watch' => $isWatch,
            'batch_rating_evidence_sha256' => $this->canonicalizer->sha256($evidence),
            'batch_rating_evidence' => $evidence,
        ];
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $metrics */
    private function batchQualityLabel(array $payload, array $metrics): string
    {
        $stored = trim((string) ($payload['prediction_status']['model_quality']['display_name'] ?? ''));
        $normalized = match (strtolower($stored)) {
            'top+' => 'Top+',
            'top', 'quality', 'strong' => 'Top',
            'solid' => 'Solid',
            'basic', 'test' => 'Basic',
            'underperform', 'unqualified', 'not qualified' => 'Underperform',
            default => null,
        };
        if ($stored !== '') {
            if ($normalized === null) {
                throw new LogicException('SOURCE_BATCH_RATING_INVALID');
            }

            return $normalized;
        }

        $trades = (int) $this->finiteMetric($metrics, 'trades', 0.0);
        $profitFactor = $this->finiteMetric($metrics, 'profit_factor', 0.0);
        $hitRate = $this->finiteMetric($metrics, 'hit_rate', 0.0);
        $average = $this->finiteMetric($metrics, 'average_net_trade', 0.0);
        $drawdown = abs($this->finiteMetric($metrics, 'max_drawdown', 1.0));

        return match (true) {
            $trades > 20 && $profitFactor >= 2.0 && $hitRate >= 0.75
                && $average > 0.02 && $drawdown < 0.25 => 'Top+',
            $trades >= 10 && $profitFactor >= 1.5 && $hitRate >= 0.66
                && $average > 0.02 && $drawdown < 0.30 => 'Top',
            $trades >= 10 && $profitFactor >= 1.25 && $hitRate >= 0.50
                && $average > 0.0 && $drawdown < 0.30 => 'Solid',
            $profitFactor > 1.0 && $average > 0.0 => 'Basic',
            default => 'Underperform',
        };
    }

    private function normalizedQualityLabel(mixed $value): string
    {
        return match (strtolower(trim((string) $value))) {
            'top+' => 'Top+',
            'top', 'quality', 'strong' => 'Top',
            'solid' => 'Solid',
            'basic', 'test' => 'Basic',
            'underperform', 'unqualified', 'not qualified' => 'Underperform',
            default => throw new LogicException('SOURCE_BATCH_RATING_INVALID'),
        };
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $metrics */
    private function batchQualityGate(array $payload, array $metrics): bool
    {
        $configured = $payload['prediction_status']['quality_gate']['passed'] ?? null;
        if ($configured !== null) {
            return $this->databaseBool($configured);
        }

        return $this->finiteMetric($metrics, 'trades', 0.0) >= 5
            && $this->finiteMetric($metrics, 'hit_rate', 0.0) >= 0.667
            && $this->finiteMetric($metrics, 'profit_factor', 0.0) >= 2.0
            && $this->finiteMetric($metrics, 'average_net_trade', 0.0) > 0.0
            && $this->finiteMetric($metrics, 'cumulative_return', 0.0) > 0.0;
    }

    /** @param array<string,mixed> $metrics */
    private function finiteMetric(array $metrics, string $key, float $default): float
    {
        $value = $metrics[$key] ?? $default;
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new LogicException('SOURCE_BATCH_RATING_INVALID');
        }

        return (float) $value;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function resolveMapping(
        int $localInstrumentId,
        array $row,
        bool $frozenOutboxIdentity = false,
    ): array {
        $locals = DB::table('instruments as instrument')
            ->join('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->where('instrument.id', $localInstrumentId)
            ->limit(2)
            ->get([
                'instrument.id', 'instrument.exchange_id', 'instrument.provider_symbol',
                'instrument.isin', 'exchange.code as exchange_code',
                'exchange.mic as exchange_mic', 'exchange.timezone as session_timezone',
            ]);
        if ($locals->count() !== 1) {
            throw new LogicException('LOCAL_INSTRUMENT_NOT_EXACTLY_ONE');
        }
        $local = (array) $locals->first();
        $timezone = trim((string) ($local['session_timezone'] ?? ''));
        if ($timezone === '') {
            throw new LogicException('LOCAL_SESSION_TIMEZONE_MISSING');
        }

        $method = null;
        $isin = $this->normalizeIdentity($row['isin'] ?? null);
        if ($isin !== '') {
            $mainCount = DB::table('instruments')
                ->whereRaw("UPPER(REGEXP_REPLACE(COALESCE(isin, ''), '[^A-Za-z0-9]', '', 'g')) = ?", [$isin])
                ->count();
            $servingCount = $frozenOutboxIdentity ? 1 : DB::connection('serving')
                ->table('serving_instruments')
                ->whereRaw("UPPER(REGEXP_REPLACE(COALESCE(isin, ''), '[^A-Za-z0-9]', '', 'g')) = ?", [$isin])
                ->count();
            if ($mainCount === 1 && $servingCount === 1
                && $this->normalizeIdentity($local['isin'] ?? null) === $isin) {
                $method = 'unique_isin';
            }
        }

        $provider = strtoupper(trim((string) ($row['provider_symbol'] ?? '')));
        $servingExchange = strtoupper(trim((string) ($row['exchange'] ?? '')));
        $mainCode = strtoupper(trim((string) ($local['exchange_code'] ?? '')));
        $mainMic = strtoupper(trim((string) ($local['exchange_mic'] ?? '')));
        if ($method === null) {
            $mainCount = DB::table('instruments')
                ->whereRaw('UPPER(provider_symbol) = ?', [$provider])
                ->where('exchange_id', (int) $local['exchange_id'])
                ->count();
            $servingCount = $frozenOutboxIdentity ? 1 : DB::connection('serving')
                ->table('serving_instruments')
                ->whereRaw('UPPER(provider_symbol) = ?', [$provider])
                ->whereRaw('UPPER(exchange) = ?', [$servingExchange])
                ->count();
            if ($provider === '' || $servingExchange === ''
                || $mainCount !== 1 || $servingCount !== 1
                || strtoupper(trim((string) ($local['provider_symbol'] ?? ''))) !== $provider
                || ! in_array($servingExchange, [$mainCode, $mainMic], true)) {
                throw new LogicException('INSTRUMENT_MAPPING_NOT_UNIQUE');
            }
            $method = 'unique_provider_symbol_exchange';
        }

        $mapping = [
            'version' => 'serving-main-instrument-map-v1',
            'method' => $frozenOutboxIdentity ? 'immutable_outbox_'.$method : $method,
            'local_instrument_id' => $localInstrumentId,
            'serving_instrument_id' => $this->positiveInt($row, 'instrument_id'),
            'isin' => $isin === '' ? null : $isin,
            'provider_symbol' => $provider,
            'serving_exchange' => $servingExchange,
            'main_exchange_code' => $mainCode,
            'main_exchange_mic' => $mainMic,
            'session_timezone' => $timezone,
        ];
        $mapping['sha256'] = $this->canonicalizer->sha256($mapping);

        return $mapping;
    }

    /** @param array<string,mixed> $feed */
    private function assertAuthoritativeFeed(
        array $feed,
        int $localInstrumentId,
        int $sourceInstrumentId,
        string $mappingHash,
        DateTimeImmutable $sourceAsOf,
        DateTimeImmutable $evaluatedAt,
        string $timezone,
        int $horizon,
    ): void {
        $reasons = $this->jsonValue($feed['reason_codes'] ?? null);
        $snapshot = $this->jsonValue($feed['verification_snapshot'] ?? null);
        if ($this->positiveInt($feed, 'id') <= 0
            || (int) ($feed['instrument_id'] ?? 0) !== $localInstrumentId
            || ($feed['provider_name'] ?? null) !== 'serving_prediction'
            || ($feed['resolver_version'] ?? null) !== self::SESSION_RESOLVER_VERSION
            || (int) ($feed['source_instrument_id'] ?? 0) !== $sourceInstrumentId
            || ! hash_equals($mappingHash, strtolower((string) ($feed['mapping_sha256'] ?? '')))
            || ($feed['status'] ?? null) !== 'READY'
            || $reasons !== []
            || ! $this->databaseBool($feed['verification_hash_valid'] ?? null)
            || preg_match(
                '/^[0-9a-f]{64}$/',
                strtolower((string) ($feed['verification_payload_sha256'] ?? '')),
            ) !== 1
            || ! is_array($snapshot)
            || (array_is_list($snapshot) && $snapshot !== [])) {
            throw new LogicException('SESSION_FEED_NOT_AUTHORITATIVELY_READY');
        }

        $ttl = filter_var($feed['heartbeat_ttl_seconds'] ?? null, FILTER_VALIDATE_INT);
        if ($ttl === false || $ttl < 30 || $ttl > 86400) {
            throw new LogicException('SESSION_FEED_TTL_INVALID');
        }
        $heartbeat = $this->timestamp($feed, 'heartbeat_at');
        $verifiedThrough = $this->timestamp($feed, 'last_verified_source_as_of');
        if ($heartbeat->add(new DateInterval('PT'.$ttl.'S')) <= $evaluatedAt
            || $heartbeat > $evaluatedAt->add(new DateInterval('PT5M'))
            || $verifiedThrough < $sourceAsOf
            || $verifiedThrough > $heartbeat) {
            throw new LogicException('SESSION_FEED_STALE_OR_WATERMARK_MISSING');
        }

        $coverageStart = $this->timestamp($snapshot, 'coverage_start_as_of');
        if (($snapshot['provider_name'] ?? null) !== 'serving_prediction'
            || ($snapshot['resolver_version'] ?? null) !== self::SESSION_RESOLVER_VERSION
            || (int) ($snapshot['local_instrument_id'] ?? 0) !== $localInstrumentId
            || (int) ($snapshot['source_instrument_id'] ?? 0) !== $sourceInstrumentId
            || ($snapshot['mapping_sha256'] ?? null) !== $mappingHash
            || ! $this->sameInstant($snapshot['heartbeat_at'] ?? null, $heartbeat)
            || ! $this->sameInstant($snapshot['verified_through_as_of'] ?? null, $verifiedThrough)
            || $coverageStart > $sourceAsOf
            || ! $this->databaseBool($snapshot['gap_free'] ?? null)
            || ! isset($snapshot['sessions'])
            || ! is_array($snapshot['sessions'])) {
            throw new LogicException('SESSION_FEED_SNAPSHOT_NOT_BOUND');
        }

        $this->assertSessionEvidence(
            $snapshot['sessions'],
            $sourceInstrumentId,
            $mappingHash,
            $coverageStart,
            $verifiedThrough,
            $sourceAsOf,
            $evaluatedAt,
            $timezone,
            $horizon,
        );
    }

    /**
     * Re-check the immutable feed contents in PHP. PostgreSQL enforces the
     * same invariants; this also makes read-only previews fail closed.
     *
     * @param  list<array<string,mixed>>  $sessions
     */
    private function assertSessionEvidence(
        array $sessions,
        int $sourceInstrumentId,
        string $mappingHash,
        DateTimeImmutable $coverageStart,
        DateTimeImmutable $verifiedThrough,
        DateTimeImmutable $sourceAsOf,
        DateTimeImmutable $evaluatedAt,
        string $timezone,
        int $horizon,
    ): void {
        $parsed = [];
        $marketDates = [];
        $eventKeys = [];
        $providerIds = [];
        foreach ($sessions as $session) {
            if (! is_array($session) || array_is_list($session)) {
                throw new LogicException('SESSION_FEED_EVIDENCE_INVALID');
            }
            $asOf = $this->timestamp($session, 'as_of');
            $marketDate = trim((string) ($session['market_date'] ?? ''));
            $eventKey = strtolower(trim((string) ($session['source_event_key'] ?? '')));
            $payloadHash = strtolower(trim((string) ($session['payload_sha256'] ?? '')));
            $batchId = trim((string) ($session['batch_id'] ?? ''));
            if (($session['source_system'] ?? null) !== 'serving_prediction_batch'
                || ($session['provider_name'] ?? null) !== 'serving_prediction'
                || ($session['mapping_sha256'] ?? null) !== $mappingHash
                || (int) ($session['serving_instrument_id'] ?? 0) !== $sourceInstrumentId
                || ($session['batch_status'] ?? null) !== 'complete'
                || ! $this->databaseBool($session['scope_active'] ?? null)
                || $batchId === ''
                || trim((string) ($session['release_id'] ?? '')) === ''
                || preg_match('/^[0-9a-f]{64}$/', $eventKey) !== 1
                || preg_match('/^[0-9a-f]{64}$/', $payloadHash) !== 1
                || $asOf < $coverageStart
                || $asOf > $verifiedThrough
                || $marketDate !== $asOf->setTimezone(new DateTimeZone($timezone))->format('Y-m-d')) {
                throw new LogicException('SESSION_FEED_EVIDENCE_INVALID');
            }
            if (isset($marketDates[$marketDate])
                || isset($eventKeys[$eventKey])
                || isset($providerIds[$batchId])) {
                throw new LogicException('SESSION_FEED_EVIDENCE_DUPLICATE');
            }
            $marketDates[$marketDate] = true;
            $eventKeys[$eventKey] = true;
            $providerIds[$batchId] = true;
            $parsed[] = [
                'as_of' => $asOf,
                'market_date' => $marketDate,
                'event_key' => $eventKey,
            ];
        }

        usort($parsed, static function (array $left, array $right): int {
            return [$left['market_date'], $left['as_of']->getTimestamp(), $left['event_key']]
                <=> [$right['market_date'], $right['as_of']->getTimestamp(), $right['event_key']];
        });
        $previousAsOf = null;
        $relevant = 0;
        $sourceMarketDate = $sourceAsOf
            ->setTimezone(new DateTimeZone($timezone))
            ->format('Y-m-d');
        foreach ($parsed as $session) {
            if ($previousAsOf !== null && $session['as_of'] <= $previousAsOf) {
                throw new LogicException('SESSION_FEED_EVIDENCE_CHRONOLOGY_INVALID');
            }
            $previousAsOf = $session['as_of'];
            if ($session['as_of'] > $sourceAsOf
                && $session['as_of'] <= $evaluatedAt
                && $session['market_date'] > $sourceMarketDate) {
                $relevant++;
            }
        }
        if ($relevant >= $horizon) {
            throw new LogicException('SOURCE_EVENT_FORECAST_HORIZON_STALE');
        }
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private function metrics(array $source): array
    {
        $qualityClass = strtolower(trim((string) ($source['model_quality_class'] ?? '')));
        $registryClass = in_array($qualityClass, ['top', 'top+'], true)
            ? 'quality'
            : $qualityClass;
        $metrics = [
            'raw_signal' => $source['signal'],
            'model_quality_class' => $registryClass,
            'quality_gate_passed' => $source['quality_gate_passed'],
            'quality_horizons' => $source['quality_horizons'],
            'country' => strtoupper(trim((string) ($source['country_code'] ?? ''))),
            'exchange' => strtoupper(trim((string) ($source['exchange'] ?? ''))),
            'sector' => strtoupper(trim((string) ($source['sector_code'] ?? ''))),
            'model_id' => (string) $source['release_id'],
        ];
        if (isset($source['expected_return']) && is_numeric($source['expected_return'])) {
            // Serving declares this column as a fraction, including values > 1.
            $metrics['predicted_return_fraction'] = $source['expected_return'];
        }
        if (isset($source['confidence']) && is_numeric($source['confidence'])) {
            $metrics['confidence_fraction'] = $source['confidence'];
        }
        $riskPercent = $this->servingRiskPercent($source['risk_score'] ?? null);
        if ($riskPercent !== null) {
            $metrics['risk_percent'] = $riskPercent;
        }
        $ratingScore = $this->servingRatingScore10(
            $source['batch_buy_rating'],
        );
        if ($ratingScore !== null) {
            $metrics['prediction_score_10'] = $ratingScore;
        }
        if ($source['performance'] !== null) {
            if (! is_array($source['performance'])) {
                throw new LogicException('SOURCE_PERFORMANCE_INVALID');
            }
            $metrics['performance'] = $source['performance'];
        }

        $context = $source['compact_context'];
        if (is_array($context) && ! array_is_list($context)) {
            foreach ([
                'confidence_fraction', 'predicted_return_fraction',
                'drawdown_fraction', 'profit_per_trade_fraction',
                'volatility_fraction', 'dividend_yield_fraction',
                'revenue_growth_fraction', 'hit_rate_fraction',
                'model_quality_fraction', 'signal_quality_fraction',
                'indicator_probability_fraction', 'market_cap',
                'dividend_yield', 'revenue_growth', 'technical_volatility_20',
                'model_quality_score', 'sector_score', 'noise_score',
                'signal_quality', 'pe_ratio', 'market_cap_group',
                'heatmap_bucket', 'ensemble_veto_passed',
                'indicator_matrix_entry_passed', 'indicator_probability_percent',
                'ai_type',
            ] as $key) {
                if (array_key_exists($key, $context)
                    && ! array_key_exists($key, $metrics)) {
                    $metrics[$key] = $context[$key];
                }
            }
        }

        return $metrics;
    }

    private function servingRiskPercent(mixed $risk): ?float
    {
        if (! is_numeric($risk)) {
            return null;
        }
        $value = (float) $risk;
        if (abs($value - round($value)) > 0.0000001
            || ! in_array((int) round($value), [1, 2, 3, 4, 5], true)) {
            return null;
        }

        return match ((int) round($value)) {
            1 => 10.0,
            2 => 30.0,
            3 => 50.0,
            4 => 70.0,
            5 => 90.0,
        };
    }

    private function servingRatingScore10(mixed $rating): ?float
    {
        $rating = str_replace('−', '-', trim((string) $rating));
        $percent = match ($rating) {
            '1++' => 99.0,
            '1+' => 95.0,
            '1' => 90.0,
            '1-' => 85.0,
            '2+' => 78.0,
            '2' => 72.0,
            '2-' => 65.0,
            '3+' => 58.0,
            '3' => 52.0,
            '3-' => 45.0,
            '4+' => 38.0,
            '4' => 32.0,
            '4-' => 25.0,
            '5+' => 18.0,
            '5' => 12.0,
            '5-' => 5.0,
            default => null,
        };

        return $percent === null ? null : $percent / 10;
    }

    /** @param array<string,mixed> $feed @return array<string,mixed> */
    private function sessionFeedEvidence(array $feed): array
    {
        return [
            'id' => (int) $feed['id'],
            'provider_name' => (string) $feed['provider_name'],
            'resolver_version' => (string) $feed['resolver_version'],
            'source_instrument_id' => (int) $feed['source_instrument_id'],
            'mapping_sha256' => strtolower((string) $feed['mapping_sha256']),
            'status' => 'READY',
            'reason_codes' => [],
            'heartbeat_at' => $this->canonicalizer->databaseTimestamp(
                $this->timestamp($feed, 'heartbeat_at'),
            ),
            'heartbeat_ttl_seconds' => (int) $feed['heartbeat_ttl_seconds'],
            'last_verified_source_as_of' => $this->canonicalizer->databaseTimestamp(
                $this->timestamp($feed, 'last_verified_source_as_of'),
            ),
            'verification_payload_sha256' => strtolower((string) $feed['verification_payload_sha256']),
            'verification_snapshot' => $this->jsonValue($feed['verification_snapshot']),
        ];
    }

    /** @param array<string,mixed> $values */
    private function timestamp(array $values, string $key): DateTimeImmutable
    {
        try {
            return $this->canonicalizer->dateTime($values[$key] ?? null);
        } catch (\Throwable) {
            throw new LogicException(strtoupper($key).'_INVALID');
        }
    }

    private function sameInstant(mixed $value, DateTimeImmutable $expected): bool
    {
        try {
            return $this->canonicalizer->utcTimestamp(
                $this->canonicalizer->dateTime($value),
            ) === $this->canonicalizer->utcTimestamp($expected);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<int> */
    private function qualityHorizons(mixed $value): array
    {
        if (! is_array($value)) {
            throw new LogicException('SOURCE_QUALITY_HORIZONS_INVALID');
        }
        $horizons = [];
        foreach ($value as $horizon) {
            $parsed = filter_var($horizon, FILTER_VALIDATE_INT);
            if ($parsed === false
                || ! in_array((int) $parsed, [5, 10, 15, 20, 40], true)) {
                throw new LogicException('SOURCE_QUALITY_HORIZONS_INVALID');
            }
            $horizons[] = (int) $parsed;
        }
        $horizons = array_values(array_unique($horizons));
        sort($horizons, SORT_NUMERIC);
        if ($horizons === []) {
            throw new LogicException('SOURCE_QUALITY_HORIZONS_INVALID');
        }

        return $horizons;
    }

    /** @param array<string,mixed> $values */
    private function positiveInt(array $values, string $key): int
    {
        $value = filter_var($values[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value <= 0) {
            throw new LogicException(strtoupper($key).'_INVALID');
        }

        return (int) $value;
    }

    /** @param array<string,mixed> $values */
    private function requiredString(array $values, string $key): string
    {
        $value = trim((string) ($values[$key] ?? ''));
        if ($value === '') {
            throw new LogicException(strtoupper($key).'_MISSING');
        }

        return $value;
    }

    private function databaseBool(mixed $value, bool $strict = true): bool
    {
        $parsed = match (true) {
            $value === true, $value === 1, $value === '1', $value === 't' => true,
            $value === false, $value === 0, $value === '0', $value === 'f' => false,
            default => null,
        };
        if ($parsed === null && $strict) {
            throw new LogicException('AUTHORITATIVE_BOOLEAN_INVALID');
        }

        return $parsed ?? false;
    }

    private function normalizeIdentity(mixed $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }

    private function jsonValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        try {
            return json_decode($value, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $value;
        }
    }
}
