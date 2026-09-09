<?php

namespace App\Services;

use App\Data\FinalEntryRawPredictionResolution;
use App\Data\FinalEntrySignalDecisionInput;
use App\Data\FinalEntrySignalDecisionResult;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Produces and, on evaluate(), persists one immutable FINAL-entry decision.
 * Nothing supplied by a caller can assert a raw signal, rule pass, clock,
 * cutover, session state or lifecycle state.
 */
class FinalEntrySignalDecisionService
{
    public const EVALUATOR_VERSION = 'final-entry-v1';

    /** @var list<string> */
    private const RAW_SIGNALS = ['BUY', 'HOLD', 'WATCH', 'WAIT', 'SELL'];

    /** @var list<string> */
    private const ELIGIBLE_QUALITY_CLASSES = [
        'basic', 'quality', 'solid', 'top', 'top+',
    ];

    public function __construct(
        private readonly FinalEntryRawPredictionResolver $rawResolver,
        private readonly FinalEntryFilterContextResolver $contextResolver,
        private readonly FinalEntryFilterRuleRegistry $rules,
        private readonly FinalEntryCanonicalizer $canonicalizer,
        private readonly ?IndicatorEntryGateService $indicatorGate = null,
    ) {}

    /** Clear command-scoped source/context memoization before a batch run. */
    public function resetRuntimeCache(): void
    {
        $this->rawResolver->resetRuntimeCache();
        $this->contextResolver->resetRuntimeCache();
    }

    /**
     * Resolve and assess the current authoritative state without writing.
     * This is a production preview, not a historical point-in-time backtest
     * API: releases, strategy settings and feed health are resolved now.
     */
    public function assess(
        FinalEntrySignalDecisionInput $input,
    ): FinalEntrySignalDecisionResult {
        $contextError = $this->validateCommand($input);
        if ($contextError !== null) {
            return FinalEntrySignalDecisionResult::failClosed([$contextError]);
        }

        try {
            $resolution = $this->rawResolver->resolve($input);
        } catch (Throwable $exception) {
            return FinalEntrySignalDecisionResult::failClosed([
                $this->safeReason($exception, 'RAW_RESOLVER_ERROR'),
            ]);
        }

        try {
            $contexts = $this->contextResolver->resolve($input);

            return $this->assessResolved($input, $resolution, $contexts);
        } catch (Throwable $exception) {
            return $this->resolvedError(
                $input,
                $resolution,
                $this->safeReason($exception, 'FILTER_CONTEXT_OR_EVALUATOR_ERROR'),
            );
        }
    }

    /** Persist a new event once, with an ACTIVE lifecycle only for ACCEPTED. */
    public function evaluate(
        FinalEntrySignalDecisionInput $input,
    ): FinalEntrySignalDecisionResult {
        $contextError = $this->validateCommand($input);
        if ($contextError !== null) {
            return FinalEntrySignalDecisionResult::failClosed([$contextError]);
        }

        try {
            return DB::transaction(function () use ($input): FinalEntrySignalDecisionResult {
                $this->lockContext($input);

                $resolution = $this->rawResolver->resolve($input);
                try {
                    $contexts = $this->contextResolver->resolve($input);
                    $candidate = $this->assessResolved($input, $resolution, $contexts);
                } catch (Throwable $exception) {
                    $candidate = $this->resolvedError(
                        $input,
                        $resolution,
                        $this->safeReason(
                            $exception,
                            'FILTER_CONTEXT_OR_EVALUATOR_ERROR',
                        ),
                    );
                }
                if (! $candidate->persistenceReady) {
                    return $candidate;
                }

                $eventKey = (string) $candidate->decision['source_event_key'];
                $existing = DB::table('entry_signal_decisions')
                    ->where('user_id', $input->userId)
                    ->where('context_key', $input->contextKey())
                    ->where('source_system', 'serving')
                    ->where('source_event_key', $eventKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    if (! hash_equals(
                        (string) $existing->source_payload_sha256,
                        (string) $candidate->decision['source_payload_sha256'],
                    )) {
                        return FinalEntrySignalDecisionResult::failClosed([
                            'SOURCE_EVENT_PAYLOAD_CONFLICT',
                        ]);
                    }

                    $lifecycle = DB::table('entry_signal_lifecycles')
                        ->where('entry_signal_decision_id', (int) $existing->id)
                        ->first();

                    return new FinalEntrySignalDecisionResult(
                        decision: $this->decodeDecisionRow((array) $existing),
                        lifecycle: $lifecycle === null
                            ? null
                            : $this->decodeLifecycleRow((array) $lifecycle),
                        decisionId: (int) $existing->id,
                        lifecycleId: $lifecycle === null ? null : (int) $lifecycle->id,
                        idempotentReplay: true,
                    );
                }

                if ($candidate->isAccepted()) {
                    $activeExists = DB::table('entry_signal_lifecycles')
                        ->where('user_id', $input->userId)
                        ->where('context_key', $input->contextKey())
                        ->where('instrument_id', $input->instrumentId)
                        ->where('status', 'ACTIVE')
                        ->lockForUpdate()
                        ->exists();
                    if ($activeExists) {
                        $candidate = $this->replaceOutcome(
                            $candidate,
                            'SUPPRESSED_ACTIVE',
                            true,
                            ['ACTIVE_LIFECYCLE_EXISTS'],
                        );
                    } elseif ($this->terminalAtOrAfter(
                        $input,
                        (string) $candidate->decision['source_as_of'],
                    )) {
                        $candidate = $this->replaceOutcome(
                            $candidate,
                            'STALE_EVENT',
                            false,
                            ['SOURCE_NOT_AFTER_PRIOR_CLOSE'],
                        );
                    }
                }

                $decisionId = $this->insertDecision($candidate->decision);
                $lifecycleId = null;
                if ($candidate->isAccepted()) {
                    $lifecycleId = $this->insertLifecycle(
                        $decisionId,
                        $input,
                        $candidate->lifecycle ?? [],
                    );
                }

                $storedDecision = DB::table('entry_signal_decisions')
                    ->where('id', $decisionId)
                    ->first();
                if ($storedDecision === null) {
                    throw new LogicException('PERSISTED_DECISION_RELOAD_FAILED');
                }
                $storedLifecycle = $lifecycleId === null
                    ? null
                    : DB::table('entry_signal_lifecycles')
                        ->where('id', $lifecycleId)
                        ->first();
                if ($lifecycleId !== null && $storedLifecycle === null) {
                    throw new LogicException('PERSISTED_LIFECYCLE_RELOAD_FAILED');
                }

                return new FinalEntrySignalDecisionResult(
                    decision: $this->decodeDecisionRow((array) $storedDecision),
                    lifecycle: $storedLifecycle === null
                        ? null
                        : $this->decodeLifecycleRow((array) $storedLifecycle),
                    decisionId: $decisionId,
                    lifecycleId: $lifecycleId,
                );
            }, 3);
        } catch (Throwable $exception) {
            return FinalEntrySignalDecisionResult::failClosed([
                $this->safeReason($exception, 'FINAL_ENTRY_PERSISTENCE_ERROR'),
            ]);
        }
    }

    /**
     * @param  list<array{origin:string,reference:string,settings:array<string,mixed>,metadata?:array<string,mixed>}>  $contexts
     */
    private function assessResolved(
        FinalEntrySignalDecisionInput $input,
        FinalEntryRawPredictionResolution $resolution,
        array $contexts,
    ): FinalEntrySignalDecisionResult {
        $rawSignal = $this->rawSignal($resolution->source['signal'] ?? null);
        $resolvedContexts = [];
        foreach ($contexts as $context) {
            if (! is_array($context)
                || trim((string) ($context['origin'] ?? '')) === ''
                || trim((string) ($context['reference'] ?? '')) === ''
                || ! isset($context['settings'])
                || ! is_array($context['settings'])
                || (array_is_list($context['settings']) && $context['settings'] !== [])) {
                throw new LogicException('FILTER_CONTEXT_EVIDENCE_INVALID');
            }
            $metadata = $context['metadata'] ?? [];
            if (! is_array($metadata)
                || (array_is_list($metadata) && $metadata !== [])) {
                throw new LogicException('FILTER_CONTEXT_METADATA_INVALID');
            }
            $resolvedContexts[] = [
                'origin' => (string) $context['origin'],
                'reference' => (string) $context['reference'],
                'settings' => $context['settings'],
                'settings_sha256' => $this->canonicalizer->sha256($context['settings']),
                'metadata' => $metadata,
                'active_rules' => [],
                'evaluation' => ['skipped' => 'RAW_SIGNAL_NOT_BUY'],
            ];
        }
        if ($resolvedContexts === []) {
            throw new LogicException('FILTER_CONTEXT_EVIDENCE_MISSING');
        }

        $metrics = $resolution->metrics;
        $metrics['raw_signal'] = $rawSignal;

        if ($rawSignal === 'UNKNOWN') {
            return $this->outcome(
                $input,
                $resolution,
                $resolvedContexts,
                $metrics,
                'ERROR',
                false,
                ['RAW_SIGNAL_UNKNOWN'],
            );
        }

        // A verified raw non-BUY is immutable passthrough. No filter can
        // promote it, and filter results are therefore intentionally absent.
        if ($rawSignal !== 'BUY') {
            return $this->outcome(
                $input,
                $resolution,
                $resolvedContexts,
                $metrics,
                'PASSTHROUGH',
                false,
                ['RAW_NOT_BUY'],
            );
        }

        $quality = strtolower(trim((string) (
            $resolution->source['model_quality_class'] ?? ''
        )));
        if ($quality === 'underperform') {
            return $this->outcome(
                $input,
                $resolution,
                $resolvedContexts,
                $metrics,
                'FILTERED',
                false,
                ['MODEL_UNDERPERFORM'],
            );
        }
        if (! in_array($quality, self::ELIGIBLE_QUALITY_CLASSES, true)) {
            return $this->outcome(
                $input,
                $resolution,
                $resolvedContexts,
                $metrics,
                'ERROR',
                false,
                ['MODEL_QUALITY_CLASS_UNKNOWN'],
            );
        }

        // Shared post-prediction gate. Raw signal remains immutable and every
        // saved strategy still ANDs with the mandatory user profile below.
        $indicator = ($this->indicatorGate ?? app(IndicatorEntryGateService::class))
            ->assessSource($resolution->source);
        $metrics['indicator_entry_gate'] = $indicator;
        if (($indicator['applied'] ?? false) === true) {
            $resolvedContexts[] = [
                'origin' => 'stock_indicator',
                'reference' => (string) ($indicator['filter_sha256'] ?? 'invalid'),
                'settings' => [],
                'settings_sha256' => $this->canonicalizer->sha256([]),
                'metadata' => $indicator,
                'active_rules' => [],
                'evaluation' => $indicator,
            ];
        }
        if (($indicator['passed'] ?? false) !== true) {
            return $this->outcome($input, $resolution, $resolvedContexts, $metrics,
                'FILTERED', false, $indicator['reason_codes'] ?? ['INDICATOR_ENTRY_REJECTED']);
        }

        $compiled = [];
        $hasEffectiveBasePolicy = false;
        foreach ($resolvedContexts as $context) {
            $definition = $this->rules->compile($context['settings']);
            $context['settings'] = $definition['audit'];
            $context['active_rules'] = $definition['active_rules'];
            $context['evaluation'] = null;
            $context['serving_scope_rule'] = $this->servingConfigurationRule(
                $definition['audit']['serving_model_configurations'] ?? null,
                $resolution->source,
            );
            if ($context['origin'] === 'user_profile'
                && $context['active_rules'] !== []) {
                $hasEffectiveBasePolicy = true;
            }
            $compiled[] = $context;
        }

        // An empty base policy must never turn a raw BUY into a FINAL BUY.
        // Saved strategies are additional contexts and remain ANDed with this
        // mandatory, server-resolved profile policy.
        if (! $hasEffectiveBasePolicy) {
            foreach ($compiled as $index => $context) {
                $compiled[$index]['evaluation'] = [
                    'passed' => false,
                    'results' => [],
                    'reasons' => ['ENTRY_BASE_POLICY_RULES_MISSING'],
                ];
            }

            return $this->outcome(
                $input,
                $resolution,
                $compiled,
                $metrics,
                'ERROR',
                false,
                ['ENTRY_BASE_POLICY_RULES_MISSING'],
            );
        }

        foreach ($compiled as $index => $context) {
            foreach ($context['active_rules'] as $rule) {
                if (($rule['key'] ?? null) !== 'entry_wait_5d_enabled') {
                    continue;
                }
                // The legacy option is an execution reservation workflow
                // (quote above highest target, reservation, five-day expiry),
                // not a raw-signal filter. Until an authoritative WAITING /
                // READY / EXPIRED state exists, accepting it here would invent
                // execution state. Keep it explicitly and persistably closed.
                $compiled[$index]['evaluation'] = [
                    'passed' => false,
                    'results' => [[
                        'key' => 'entry_wait_5d_enabled',
                        'scope' => 'entry',
                        'evaluated' => false,
                        'passed' => false,
                        'reason' => 'ENTRY_WAIT_STATE_UNAVAILABLE',
                    ]],
                    'reasons' => ['ENTRY_WAIT_STATE_UNAVAILABLE'],
                ];

                return $this->outcome(
                    $input,
                    $resolution,
                    $compiled,
                    $metrics,
                    'ERROR',
                    false,
                    ['ENTRY_WAIT_STATE_UNAVAILABLE'],
                );
            }
        }

        $allPassed = true;
        $reasons = [];
        foreach ($compiled as $index => $context) {
            $evaluation = $this->rules->evaluate($context['active_rules'], $metrics);
            $compiled[$index]['evaluation'] = $evaluation;
            $scopeRule = $context['serving_scope_rule'];
            $allPassed = $allPassed && $evaluation['passed'] && $scopeRule['passed'];
            $reasons = array_merge($reasons, $evaluation['reasons']);
            if (! $scopeRule['passed']) {
                $reasons[] = $scopeRule['reason'];
            }
        }
        $reasons = array_values(array_unique($reasons));

        if (! $allPassed) {
            $onlyOrdinaryRejections = $reasons !== [];
            foreach ($reasons as $reason) {
                if (! str_starts_with($reason, 'FILTER_REJECTED:')
                    && $reason !== 'FILTER_INPUT_MISSING:entry_wait_5d_enabled') {
                    $onlyOrdinaryRejections = false;
                    break;
                }
            }

            return $this->outcome(
                $input,
                $resolution,
                $compiled,
                $metrics,
                $onlyOrdinaryRejections ? 'FILTERED' : 'ERROR',
                false,
                $reasons === [] ? ['FILTER_EVALUATOR_ERROR'] : $reasons,
            );
        }

        return $this->outcome(
            $input,
            $resolution,
            $compiled,
            $metrics,
            'ACCEPTED',
            true,
            [],
        );
    }

    /**
     * A saved strategy's resolved serving configurations are a strict scope
     * whitelist, even though the generic registry correctly treats the JSON
     * container itself as non-execution UI metadata.
     *
     * @param  array<string,mixed>  $source
     * @return array<string,mixed>
     */
    private function servingConfigurationRule(mixed $configurations, array $source): array
    {
        if ($configurations === null || $configurations === []) {
            return [
                'key' => 'serving_model_configurations',
                'evaluated' => false,
                'passed' => true,
                'reason' => null,
            ];
        }
        if (! is_array($configurations) || ! array_is_list($configurations)) {
            return [
                'key' => 'serving_model_configurations',
                'evaluated' => true,
                'passed' => false,
                'reason' => 'FILTER_CONFIG_INVALID:serving_model_configurations',
            ];
        }

        $sourceSymbol = strtoupper(trim((string) ($source['symbol'] ?? '')));
        $sourceRelease = trim((string) ($source['release_id'] ?? ''));
        $sourceHorizon = (int) ($source['horizon'] ?? 0);
        $sourceVariant = trim((string) ($source['variant'] ?? ''));
        $valid = true;
        $matched = null;
        foreach ($configurations as $configuration) {
            if (! is_array($configuration) || array_is_list($configuration)) {
                $valid = false;
                break;
            }
            $symbol = strtoupper(trim((string) ($configuration['symbol'] ?? '')));
            $release = trim((string) ($configuration['release_id'] ?? ''));
            $releasePolicy = (string) ($configuration['release_policy'] ?? 'fixed');
            $horizon = filter_var(
                $configuration['horizon'] ?? $configuration['horizon_days'] ?? null,
                FILTER_VALIDATE_INT,
            );
            $horizonDays = array_key_exists('horizon_days', $configuration)
                ? filter_var($configuration['horizon_days'], FILTER_VALIDATE_INT)
                : $horizon;
            $variant = trim((string) ($configuration['variant'] ?? ''));
            if ($symbol === '' || ! in_array($releasePolicy, ['fixed', 'active'], true)
                || ($releasePolicy === 'fixed' && $release === '') || $horizon === false
                || $horizon <= 0 || $horizonDays === false
                || (int) $horizonDays !== (int) $horizon || $variant === '') {
                $valid = false;
                break;
            }
            if ($symbol === $sourceSymbol
                && ($releasePolicy === 'active' || $release === $sourceRelease)
                && (int) $horizon === $sourceHorizon
                && $variant === $sourceVariant) {
                $matched = $configuration;
            }
        }

        return [
            'key' => 'serving_model_configurations',
            'evaluated' => true,
            'source_tuple' => [
                'symbol' => $sourceSymbol,
                'release_id' => $sourceRelease,
                'horizon' => $sourceHorizon,
                'variant' => $sourceVariant,
            ],
            'matched_configuration' => $matched,
            'passed' => $valid && $matched !== null,
            'reason' => ! $valid
                ? 'FILTER_CONFIG_INVALID:serving_model_configurations'
                : ($matched === null
                    ? 'FILTER_REJECTED:serving_model_configurations'
                    : null),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $contexts
     * @param  array<string,mixed>  $metrics
     * @param  list<string>  $reasons
     */
    private function outcome(
        FinalEntrySignalDecisionInput $input,
        FinalEntryRawPredictionResolution $resolution,
        array $contexts,
        array $metrics,
        string $status,
        bool $filtersPassed,
        array $reasons,
    ): FinalEntrySignalDecisionResult {
        $rawSignal = $this->rawSignal($resolution->source['signal'] ?? null);
        $filterSnapshot = [
            'schema' => 'final-entry-filter-evidence-v1',
            'evaluator_version' => self::EVALUATOR_VERSION,
            'resolved_at' => $this->canonicalizer->databaseTimestamp($resolution->evaluatedAt),
            'source_event_key' => $resolution->sourceEventKey,
            'metrics_sha256' => $this->canonicalizer->sha256($metrics),
            'metrics' => $metrics,
            'contexts' => $contexts,
        ];
        $filterHash = $this->canonicalizer->sha256($filterSnapshot);
        $signal = match ($status) {
            'ACCEPTED' => 'BUY',
            'PASSTHROUGH' => $rawSignal,
            'FILTERED' => 'WATCH',
            default => 'HOLD',
        };

        $decision = $this->baseDecision(
            $input,
            $resolution,
            $filterSnapshot,
            $filterHash,
            $rawSignal,
        );
        $decision['filters_passed'] = $filtersPassed;
        $decision['decision_signal'] = $signal;
        $decision['decision_status'] = $status;
        $decision['reason_codes'] = array_values(array_unique($reasons));

        return new FinalEntrySignalDecisionResult(
            decision: $decision,
            lifecycle: $status === 'ACCEPTED'
                ? $this->lifecycleTemplate($resolution)
                : null,
        );
    }

    private function resolvedError(
        FinalEntrySignalDecisionInput $input,
        FinalEntryRawPredictionResolution $resolution,
        string $reason,
    ): FinalEntrySignalDecisionResult {
        try {
            return $this->outcome(
                $input,
                $resolution,
                [[
                    'origin' => 'resolution_error',
                    'reference' => 'unresolved',
                    'settings' => [],
                    'settings_sha256' => $this->canonicalizer->sha256([]),
                    'active_rules' => [],
                    'evaluation' => [
                        'passed' => false,
                        'results' => [],
                        'reasons' => [$reason],
                    ],
                ]],
                ['raw_signal' => $this->rawSignal($resolution->source['signal'] ?? null)],
                'ERROR',
                false,
                [$reason],
            );
        } catch (Throwable) {
            return FinalEntrySignalDecisionResult::failClosed([$reason]);
        }
    }

    /**
     * @param  array<string,mixed>  $filterSnapshot
     * @return array<string,mixed>
     */
    private function baseDecision(
        FinalEntrySignalDecisionInput $input,
        FinalEntryRawPredictionResolution $resolution,
        array $filterSnapshot,
        string $filterHash,
        string $rawSignal,
    ): array {
        $source = $resolution->source;

        return [
            'user_id' => $input->userId,
            'context_type' => $input->contextType,
            'context_key' => $input->contextKey(),
            'saved_prediction_filter_id' => $input->savedPredictionFilterId,
            'saved_prediction_filter_id_snapshot' => $input->savedPredictionFilterId,
            'instrument_id' => $input->instrumentId,
            'source_system' => 'serving',
            'source_prediction_id' => (int) $source['id'],
            'source_event_key' => $resolution->sourceEventKey,
            'source_payload_sha256' => $resolution->sourcePayloadSha256,
            'source_batch_id' => (string) $source['batch_id'],
            'source_release_id' => (string) $source['release_id'],
            'source_instrument_id' => (int) $source['instrument_id'],
            'source_as_of' => (string) $source['as_of'],
            'source_batch_completed_at' => (string) $source['batch_completed_at'],
            'source_market_date' => (string) $source['market_date'],
            'forecast_horizon_sessions' => (int) $source['horizon'],
            'source_variant' => (string) $source['variant'],
            'raw_signal' => $rawSignal,
            'model_quality_class' => $source['model_quality_class'] ?? null,
            'filters_passed' => false,
            'decision_signal' => 'HOLD',
            'decision_status' => 'ERROR',
            'reason_codes' => ['UNASSESSED'],
            'source_snapshot' => $source,
            'filter_snapshot' => $filterSnapshot,
            'filter_sha256' => $filterHash,
            'evaluator_version' => self::EVALUATOR_VERSION,
            'cutover_at' => $this->canonicalizer->databaseTimestamp($resolution->cutoverAt),
            'evaluated_at' => $this->canonicalizer->databaseTimestamp($resolution->evaluatedAt),
        ];
    }

    /** @return array<string,mixed> */
    private function lifecycleTemplate(
        FinalEntryRawPredictionResolution $resolution,
    ): array {
        $feed = $resolution->sessionFeed;

        return [
            'activated_at' => $this->canonicalizer->databaseTimestamp($resolution->evaluatedAt),
            'expiry_market_date' => null,
            'expires_at' => null,
            'session_feed_state_id' => (int) $feed['id'],
            'session_feed_provider' => (string) $feed['provider_name'],
            'session_feed_resolver_version' => (string) $feed['resolver_version'],
            'session_mapping_sha256' => (string) $feed['mapping_sha256'],
            'session_timezone' => (string) $resolution->mapping['session_timezone'],
            'observed_sessions' => 0,
            'last_observed_market_date' => null,
            'last_observed_as_of' => null,
            'session_evidence_sha256' => $this->canonicalizer->sha256([]),
            'session_evidence' => [],
            'session_clock_updated_at' => $this->canonicalizer->databaseTimestamp($resolution->evaluatedAt),
            'session_feed_status' => 'READY',
            'session_feed_reason_codes' => [],
            'configured_exit_profile_id' => null,
            'configured_exit_profile_signature' => null,
            'configured_exit_policy_name' => null,
            'configured_exit_policy_version' => null,
            'configured_exit_snapshot' => null,
        ];
    }

    /** @param array<string,mixed> $decision */
    private function insertDecision(array $decision): int
    {
        foreach (['reason_codes', 'source_snapshot', 'filter_snapshot'] as $key) {
            $decision[$key] = $this->canonicalizer->json($decision[$key]);
        }
        $decision['created_at'] = $decision['evaluated_at'];
        $decision['updated_at'] = $decision['evaluated_at'];

        return (int) DB::table('entry_signal_decisions')->insertGetId($decision);
    }

    /** @param array<string,mixed> $lifecycle */
    private function insertLifecycle(
        int $decisionId,
        FinalEntrySignalDecisionInput $input,
        array $lifecycle,
    ): int {
        $lifecycle = array_merge($lifecycle, [
            'entry_signal_decision_id' => $decisionId,
            'user_id' => $input->userId,
            'context_key' => $input->contextKey(),
            'instrument_id' => $input->instrumentId,
            'decision_status' => 'ACCEPTED',
            'status' => 'ACTIVE',
        ]);
        foreach ([
            'session_evidence', 'session_feed_reason_codes',
            'configured_exit_snapshot',
        ] as $key) {
            if (array_key_exists($key, $lifecycle) && $lifecycle[$key] !== null) {
                $lifecycle[$key] = $this->canonicalizer->json($lifecycle[$key]);
            }
        }
        $lifecycle['created_at'] = $lifecycle['activated_at'];
        $lifecycle['updated_at'] = $lifecycle['activated_at'];

        return (int) DB::table('entry_signal_lifecycles')->insertGetId($lifecycle);
    }

    private function lockContext(FinalEntrySignalDecisionInput $input): void
    {
        DB::selectOne(
            'SELECT pg_advisory_xact_lock(hashtext(?))',
            [$input->userId.':'.$input->contextKey().':'.$input->instrumentId],
        );
    }

    private function terminalAtOrAfter(
        FinalEntrySignalDecisionInput $input,
        string $sourceAsOf,
    ): bool {
        return DB::table('entry_signal_lifecycles')
            ->where('user_id', $input->userId)
            ->where('context_key', $input->contextKey())
            ->where('instrument_id', $input->instrumentId)
            ->whereIn('status', ['CLOSED_EXIT', 'EXPIRED'])
            ->where('closed_effective_as_of', '>=', $sourceAsOf)
            ->lockForUpdate()
            ->exists();
    }

    /** @param list<string> $reasons */
    private function replaceOutcome(
        FinalEntrySignalDecisionResult $candidate,
        string $status,
        bool $filtersPassed,
        array $reasons,
    ): FinalEntrySignalDecisionResult {
        $decision = $candidate->decision;
        $decision['filters_passed'] = $filtersPassed;
        $decision['decision_signal'] = 'HOLD';
        $decision['decision_status'] = $status;
        $decision['reason_codes'] = $reasons;

        return new FinalEntrySignalDecisionResult($decision);
    }

    private function validateCommand(FinalEntrySignalDecisionInput $input): ?string
    {
        if ($input->userId <= 0
            || $input->instrumentId <= 0
            || $input->sourcePredictionId <= 0) {
            return 'ENTRY_COMMAND_ID_INVALID';
        }
        if ($input->contextType === 'user_profile') {
            return $input->savedPredictionFilterId === null
                ? null
                : 'ENTRY_CONTEXT_INVALID';
        }
        if ($input->contextType !== 'saved_filter'
            || $input->savedPredictionFilterId === null
            || $input->savedPredictionFilterId <= 0) {
            return 'ENTRY_CONTEXT_INVALID';
        }

        return null;
    }

    private function rawSignal(mixed $value): string
    {
        $signal = strtoupper(trim((string) $value));

        return in_array($signal, self::RAW_SIGNALS, true) ? $signal : 'UNKNOWN';
    }

    private function safeReason(Throwable $exception, string $fallback): string
    {
        $message = trim($exception->getMessage());
        if (preg_match('/^[A-Z][A-Z0-9_]*(?::[A-Za-z0-9_.+-]+)?$/', $message) === 1) {
            return $message;
        }

        return $fallback;
    }

    /** @return array<string,mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return [];
        }
        try {
            $decoded = json_decode($value, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function decodeDecisionRow(array $row): array
    {
        foreach (['reason_codes', 'source_snapshot', 'filter_snapshot'] as $key) {
            $row[$key] = $this->decodeJson($row[$key] ?? null);
        }
        $row['filters_passed'] = (bool) ($row['filters_passed'] ?? false);

        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function decodeLifecycleRow(array $row): array
    {
        foreach ([
            'session_evidence', 'session_feed_reason_codes',
            'configured_exit_snapshot', 'entry_consumer_reference', 'exit_snapshot',
        ] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $row[$key] = $this->decodeJson($row[$key]);
            }
        }

        return $row;
    }
}
