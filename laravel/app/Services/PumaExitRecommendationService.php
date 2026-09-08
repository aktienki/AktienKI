<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use LogicException;
use RuntimeException;
use stdClass;

final class PumaExitRecommendationService
{
    public const POLICY_NAME = 'pum_short_curve_trend_p55_v2';

    public const POLICY_VERSION = 'pum-long-exit-s2-l-or-trend-p55-v1';

    public const SELECTION_PROCEDURE_VERSION = 'pum-short-indicator-long-exit-combination-search-v2';

    public const CORE_POLICY_FINGERPRINT = 'c216205e3f7d35f9752f60acac9d02a717339f11c537d47dbf384e6ee2905f79';

    public const INSTRUMENT_ID = 1085;

    public const ISIN = 'DE0006969603';

    public const PROVIDER_SYMBOL = 'PUM:XETR';

    public const SYMBOL = 'PUM';

    public const MARKET_CALENDAR = 'XETRA';

    public const FORMULA = '(S_2 & L) | I_trend_momentum_probability_p55';

    public const EXECUTION_TIME = 'next_xetra_adjusted_open';

    public const LONG_CURVE_THRESHOLD = -0.05;

    public const TREND_MOMENTUM_PROBABILITY_THRESHOLD = 0.55;

    public const INPUT_SCHEMA_VERSION = 1;

    public const BUNDLE_MANIFEST_SHA256 = '59b6648d5a03fdafbd2e0233c584a49d723e48b16b617163b1373a233bf62f45';

    public const BUNDLE_POLICY_JSON_SHA256 = 'b4f47bfef6ba2554fc18131909380e96be07eb1311e55ed7f46c34ba60a9b0ff';

    public const BUNDLE_SOURCE_PATH = '/Users/aktienki/pipeline-data/model-releases/pum-long-exit/pum-long-exit-s2-l-or-trend-p55-v1';

    /** @var list<string> */
    private const RUNNER_PAYLOAD_FIELDS = [
        'schema_version',
        'policy_name',
        'policy_version',
        'core_policy_fingerprint',
        'instrument_id',
        'symbol',
        'isin',
        'provider_symbol',
        'market_calendar',
        'market_session_date',
        'market_session_position',
        'signal_as_of',
        'valid_until',
        'observation_role',
        'state_only',
        'short_shock_10t',
        'short_shock_10t_score',
        'short_lower_tail_20t',
        'short_lower_tail_20t_score',
        'short_bear_area_40t',
        'short_bear_area_40t_score',
        'champion_10t',
        'champion_40t',
        'trend_momentum_probability',
        'source_lineage',
        'scorer_diagnostics',
    ];

    /**
     * Append one immutable, post-close PUMA observation to the serving database.
     *
     * Replaying the byte-semantically identical input for a market date returns
     * the existing observation. A changed input for that date is rejected.
     *
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RuntimeException
     */
    public function import(array $payload): array
    {
        $batch = $this->importMany([$payload]);

        return $batch['observations'][0];
    }

    /**
     * Atomically append a chronological catch-up batch.
     *
     * The whole batch is normalized before its transaction starts. Existing
     * rows with the same canonical input hash are returned as replays; any
     * conflict or insertion error rolls every new row in this call back.
     */
    public function importMany(array $payloads): array
    {
        self::assertFrozenPolicyFingerprint();
        if ($payloads === [] || ! array_is_list($payloads)) {
            throw new InvalidArgumentException('PUMA exit imports must be a non-empty list of observations.');
        }

        $inputs = [];
        foreach ($payloads as $index => $payload) {
            if (! is_array($payload) || array_is_list($payload)) {
                throw new InvalidArgumentException('PUMA exit observation '.($index + 1).' must be a JSON object.');
            }

            try {
                $normalized = self::normalizePayload($payload);
                $inputs[] = [
                    'payload' => $normalized,
                    'input_sha256' => self::inputHash($normalized),
                ];
            } catch (InvalidArgumentException $exception) {
                throw new InvalidArgumentException(
                    'PUMA exit observation '.($index + 1).' is invalid: '.$exception->getMessage(),
                    0,
                    $exception,
                );
            }
        }

        for ($index = 1, $count = count($inputs); $index < $count; $index++) {
            $previous = $inputs[$index - 1]['payload'];
            $current = $inputs[$index]['payload'];
            if ($current['market_session_date'] <= $previous['market_session_date']) {
                throw new InvalidArgumentException(
                    'NDJSON observations must have strictly increasing market_session_date values; '
                    .'violation at observation '.($index + 1).'.'
                );
            }
            if ($current['market_session_position'] <= $previous['market_session_position']) {
                throw new InvalidArgumentException(
                    'NDJSON observations must have strictly increasing market_session_position values; '
                    .'violation at observation '.($index + 1).'.'
                );
            }
        }

        $connection = DB::connection('serving');

        return $connection->transaction(function () use ($connection, $inputs): array {
            $instrument = $connection->table('serving_instruments')
                ->where('id', self::INSTRUMENT_ID)
                ->lockForUpdate()
                ->first(['id', 'isin', 'provider_symbol']);
            self::assertCanonicalInstrument($instrument);

            $policy = $connection->table('serving_exit_policies')
                ->where('policy_name', self::POLICY_NAME)
                ->lockForUpdate()
                ->first();
            self::assertActivePolicy($policy);

            $latest = $connection->table('serving_exit_signal_observations')
                ->where('policy_name', self::POLICY_NAME)
                ->orderByDesc('market_session_position')
                ->lockForUpdate()
                ->first();
            $results = [];
            $importedCount = 0;
            $replayedCount = 0;

            foreach ($inputs as $item) {
                $input = $item['payload'];
                $inputHash = $item['input_sha256'];
                $existing = $connection->table('serving_exit_signal_observations')
                    ->where('policy_name', self::POLICY_NAME)
                    ->where('market_session_date', $input['market_session_date'])
                    ->first();

                if ($existing !== null) {
                    if (! hash_equals(trim((string) $existing->input_sha256), $inputHash)) {
                        throw new LogicException(
                            "A different PUMA exit input already exists for {$input['market_session_date']}. "
                            .'The complete catch-up transaction was rolled back.'
                        );
                    }

                    $results[] = [
                        ...self::applyFreshnessGuard(self::observationToArray($existing)),
                        'idempotent_replay' => true,
                    ];
                    $replayedCount++;

                    continue;
                }

                if ($latest !== null
                    && $input['market_session_position'] <= (int) $latest->market_session_position) {
                    throw new LogicException('PUMA exit observations must be appended in market-session order.');
                }

                if ($latest !== null
                    && $input['market_session_date'] <= (string) $latest->market_session_date) {
                    throw new LogicException('PUMA exit observation dates must increase.');
                }

                $derived = self::evaluateSignals($input, $latest === null ? null : [
                    'market_session_position' => (int) $latest->market_session_position,
                    'long_curve_raw' => self::databaseBoolean($latest->long_curve_raw),
                    'indicator_raw' => self::databaseBoolean($latest->indicator_raw),
                ]);
                $id = $connection->table('serving_exit_signal_observations')->insertGetId(
                    self::observationInsert($input, $inputHash, $derived),
                    'id',
                );
                $inserted = $connection->table('serving_exit_signal_observations')
                    ->where('id', $id)
                    ->first();

                if ($inserted === null) {
                    throw new RuntimeException('The inserted PUMA exit observation cannot be read back.');
                }

                $latest = $inserted;
                $results[] = [
                    ...self::applyFreshnessGuard(self::observationToArray($inserted)),
                    'idempotent_replay' => false,
                ];
                $importedCount++;
            }

            return [
                'policy_name' => self::POLICY_NAME,
                'policy_version' => self::POLICY_VERSION,
                'observation_count' => count($results),
                'imported_count' => $importedCount,
                'idempotent_replay_count' => $replayedCount,
                'first_market_session_date' => $inputs[0]['payload']['market_session_date'],
                'last_market_session_date' => $inputs[array_key_last($inputs)]['payload']['market_session_date'],
                'observations' => $results,
            ];
        });
    }

    /**
     * Read the active PUMA recommendation without touching a position or order.
     */
    public function current(): array
    {
        self::assertFrozenPolicyFingerprint();
        $row = DB::connection('serving')
            ->table('serving_current_exit_recommendations')
            ->where('policy_name', self::POLICY_NAME)
            ->where('instrument_id', self::INSTRUMENT_ID)
            ->first();

        if ($row === null) {
            throw new RuntimeException('The active PUMA exit recommendation policy is not installed.');
        }
        self::assertActivePolicy($row);

        $result = (array) $row;
        foreach (['configuration', 'evidence', 'source_lineage'] as $jsonColumn) {
            if (isset($result[$jsonColumn]) && is_string($result[$jsonColumn])) {
                $result[$jsonColumn] = json_decode($result[$jsonColumn], true, 512, JSON_THROW_ON_ERROR);
            }
        }
        foreach ([
            'is_active',
            'recommendation_only',
            'automatic_execution',
            'release_gate_passed',
            'user_override',
            'short_shock_10t',
            'short_lower_tail_20t',
            'short_bear_area_40t',
            'long_curve_raw',
            'long_curve_confirmed',
            'indicator_raw',
            'indicator_confirmed',
            'model_exit_recommended',
            'exit_recommended',
            'recommendation_actionable',
            'state_only',
        ] as $booleanColumn) {
            if (array_key_exists($booleanColumn, $result) && $result[$booleanColumn] !== null) {
                $result[$booleanColumn] = self::databaseBoolean($result[$booleanColumn]);
            }
        }

        return self::applyFreshnessGuard($result);
    }

    /**
     * Defend the read path against a stale or state-only observation even if a
     * consumer is running against a cached/older view definition.
     */
    public static function applyFreshnessGuard(
        array $result,
        ?CarbonImmutable $now = null,
    ): array {
        $result['model_decision'] ??= $result['decision'] ?? 'NO_DATA';
        $result['model_exit_recommended'] = self::databaseBoolean(
            $result['model_exit_recommended'] ?? $result['exit_recommended'] ?? false
        );
        $result['exit_recommended'] = false;
        $result['recommendation_actionable'] = false;

        if (($result['observation_id'] ?? $result['id'] ?? null) === null) {
            $result['recommendation_status'] = 'NO_DATA';
            $result['decision'] = 'NO_DATA';

            return $result;
        }

        $stateOnly = self::databaseBoolean($result['state_only'] ?? false);
        $role = (string) ($result['observation_role'] ?? '');
        if ($stateOnly) {
            if (! in_array($role, ['state_seed', 'historical_backfill'], true)) {
                throw new RuntimeException('A state-only PUMA observation has an invalid role.');
            }
            $result['recommendation_status'] = 'STATE_ONLY';
            $result['decision'] = 'NO_DATA';

            return $result;
        }

        if ($role !== 'current_recommendation') {
            throw new RuntimeException('A non-state-only PUMA observation is not a current recommendation.');
        }

        $signalAsOf = self::databaseTimestamp($result['signal_as_of'] ?? null, 'signal_as_of');
        $validUntil = self::databaseTimestamp($result['valid_until'] ?? null, 'valid_until');
        if (! $signalAsOf->lessThan($validUntil)) {
            throw new RuntimeException('The PUMA recommendation timing contract is invalid.');
        }

        $now ??= CarbonImmutable::now('UTC');
        if (! $now->lessThan($validUntil)) {
            $result['recommendation_status'] = 'EXPIRED';
            $result['decision'] = 'NO_DATA';

            return $result;
        }

        if (! in_array($result['model_decision'], ['HOLD', 'EXIT_RECOMMENDED'], true)) {
            throw new RuntimeException('The PUMA model decision is invalid.');
        }

        $result['recommendation_status'] = 'CURRENT';
        $result['decision'] = $result['model_decision'];
        $result['exit_recommended'] = $result['model_exit_recommended'];
        $result['recommendation_actionable'] = $result['model_exit_recommended'];

        return $result;
    }

    /**
     * Pure implementation of the fixed formula, also used by focused tests.
     */
    public static function evaluateSignals(array $current, ?array $previous): array
    {
        $shortVoteCount = (int) $current['short_shock_10t']
            + (int) $current['short_lower_tail_20t']
            + (int) $current['short_bear_area_40t'];
        $longCurveRaw = $current['champion_40t'] <= self::LONG_CURVE_THRESHOLD
            && $current['champion_40t'] < $current['champion_10t'];
        $indicatorRaw = $current['trend_momentum_probability']
            >= self::TREND_MOMENTUM_PROBABILITY_THRESHOLD;
        $consecutiveSession = $previous !== null
            && (int) $current['market_session_position']
                === (int) $previous['market_session_position'] + 1;
        $longCurveConfirmed = $longCurveRaw
            && $consecutiveSession
            && (bool) ($previous['long_curve_raw'] ?? false);
        $indicatorConfirmed = $indicatorRaw
            && $consecutiveSession
            && (bool) ($previous['indicator_raw'] ?? false);
        $exitRecommended = ($shortVoteCount >= 2 && $longCurveConfirmed)
            || $indicatorConfirmed;

        return [
            'short_vote_count' => $shortVoteCount,
            'long_curve_raw' => $longCurveRaw,
            'long_curve_confirmed' => $longCurveConfirmed,
            'indicator_raw' => $indicatorRaw,
            'indicator_confirmed' => $indicatorConfirmed,
            'exit_recommended' => $exitRecommended,
            'decision' => $exitRecommended ? 'EXIT_RECOMMENDED' : 'HOLD',
            'execution_timing' => self::EXECUTION_TIME,
            'automatic_execution' => false,
            'maximum_holding_sessions' => null,
        ];
    }

    /**
     * Validate and canonicalize the JSON import contract.
     */
    public static function normalizePayload(array $payload): array
    {
        $unknownFields = array_values(array_diff(array_keys($payload), self::RUNNER_PAYLOAD_FIELDS));
        if ($unknownFields !== []) {
            throw new InvalidArgumentException(
                'PUMA exit payload contains unsupported top-level fields: '
                .implode(', ', $unknownFields).'.'
            );
        }

        self::requireExact($payload, 'schema_version', self::INPUT_SCHEMA_VERSION);
        self::requireExact($payload, 'policy_name', self::POLICY_NAME);
        self::requireExact($payload, 'policy_version', self::POLICY_VERSION);
        self::requireExact($payload, 'core_policy_fingerprint', self::CORE_POLICY_FINGERPRINT);
        self::requireExact($payload, 'instrument_id', self::INSTRUMENT_ID);
        self::requireExact($payload, 'symbol', self::SYMBOL);
        self::requireExact($payload, 'isin', self::ISIN);
        self::requireExact($payload, 'provider_symbol', self::PROVIDER_SYMBOL);
        self::requireExact($payload, 'market_calendar', self::MARKET_CALENDAR);

        $marketDate = self::marketDate($payload['market_session_date'] ?? null);
        $marketPosition = $payload['market_session_position'] ?? null;
        if (! is_int($marketPosition) || $marketPosition < 0) {
            throw new InvalidArgumentException('market_session_position must be a non-negative integer.');
        }

        [$signalAsOf, $validUntil, $observationRole, $stateOnly] = self::timingContract(
            $payload,
            $marketDate,
        );
        $sourceLineage = $payload['source_lineage'] ?? null;
        if (! is_array($sourceLineage) || $sourceLineage === [] || array_is_list($sourceLineage)) {
            throw new InvalidArgumentException('source_lineage must be a non-empty JSON object.');
        }
        self::validateSourceLineage($sourceLineage);
        self::validateScorerDiagnostics($payload['scorer_diagnostics'] ?? null);

        return [
            'schema_version' => self::INPUT_SCHEMA_VERSION,
            'policy_name' => self::POLICY_NAME,
            'policy_version' => self::POLICY_VERSION,
            'core_policy_fingerprint' => self::CORE_POLICY_FINGERPRINT,
            'instrument_id' => self::INSTRUMENT_ID,
            'symbol' => self::SYMBOL,
            'isin' => self::ISIN,
            'provider_symbol' => self::PROVIDER_SYMBOL,
            'market_calendar' => self::MARKET_CALENDAR,
            'market_session_date' => $marketDate,
            'market_session_position' => $marketPosition,
            'signal_as_of' => $signalAsOf,
            'valid_until' => $validUntil,
            'observation_role' => $observationRole,
            'state_only' => $stateOnly,
            'short_shock_10t' => self::requiredBoolean($payload, 'short_shock_10t'),
            'short_shock_10t_score' => self::requiredFiniteNumber($payload, 'short_shock_10t_score'),
            'short_lower_tail_20t' => self::requiredBoolean($payload, 'short_lower_tail_20t'),
            'short_lower_tail_20t_score' => self::requiredFiniteNumber(
                $payload,
                'short_lower_tail_20t_score'
            ),
            'short_bear_area_40t' => self::requiredBoolean($payload, 'short_bear_area_40t'),
            'short_bear_area_40t_score' => self::requiredFiniteNumber(
                $payload,
                'short_bear_area_40t_score'
            ),
            'champion_10t' => self::requiredFiniteNumber($payload, 'champion_10t'),
            'champion_40t' => self::requiredFiniteNumber($payload, 'champion_40t'),
            'trend_momentum_probability' => self::requiredProbability(
                $payload,
                'trend_momentum_probability'
            ),
            'source_lineage' => self::canonicalize($sourceLineage),
        ];
    }

    public static function inputHash(array $normalizedPayload): string
    {
        try {
            $json = json_encode(
                self::canonicalize($normalizedPayload),
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The PUMA exit payload cannot be canonically encoded.', 0, $exception);
        }

        return hash('sha256', $json);
    }

    /**
     * This payload is intentionally identical to pipeline-next's frozen
     * PUM_LONG_EXIT_POLICY dataclass. It is the material behind the reviewed
     * core policy fingerprint.
     */
    public static function policyContract(): array
    {
        return [
            'policy_version' => self::POLICY_VERSION,
            'selection_procedure_version' => self::SELECTION_PROCEDURE_VERSION,
            'formula' => self::FORMULA,
            'symbol' => self::SYMBOL,
            'provider_symbol' => self::PROVIDER_SYMBOL,
            'isin' => self::ISIN,
            'market_calendar' => self::MARKET_CALENDAR,
            'short_model_names' => [
                'shock_hurdle_rf_10t',
                'lower_tail_hgb_20t',
                'bear_area_tcn_40t',
            ],
            'minimum_short_votes' => 2,
            'long_curve_threshold' => self::LONG_CURVE_THRESHOLD,
            'long_curve_confirmation_sessions' => 2,
            'indicator_model_name' => 'trend_momentum_probability',
            'indicator_probability_threshold' => self::TREND_MOMENTUM_PROBABILITY_THRESHOLD,
            'indicator_confirmation_sessions' => 2,
            'signal_time' => 'after_xetra_close',
            'execution_time' => self::EXECUTION_TIME,
            'maximum_holding_sessions' => null,
            'entry_veto' => false,
        ];
    }

    public static function assertFrozenPolicyFingerprint(): void
    {
        try {
            $json = json_encode(
                self::canonicalize(self::policyContract()),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('The frozen PUMA policy cannot be encoded.', 0, $exception);
        }

        if (! hash_equals(self::CORE_POLICY_FINGERPRINT, hash('sha256', $json))) {
            throw new RuntimeException(
                'The frozen PUMA policy changed without a new reviewed fingerprint.'
            );
        }
    }

    public static function assertCanonicalInstrument(?object $instrument): void
    {
        if ($instrument === null
            || (int) ($instrument->id ?? 0) !== self::INSTRUMENT_ID
            || (string) ($instrument->isin ?? '') !== self::ISIN
            || (string) ($instrument->provider_symbol ?? '') !== self::PROVIDER_SYMBOL) {
            throw new RuntimeException(
                'Serving identity mismatch: expected 1085/DE0006969603/PUM:XETR.'
            );
        }
    }

    private static function assertActivePolicy(?object $policy): void
    {
        if ($policy === null) {
            throw new RuntimeException('The active PUMA exit recommendation policy is not installed.');
        }

        $status = (string) ($policy->policy_status ?? $policy->status ?? '');
        if ((string) ($policy->policy_name ?? '') !== self::POLICY_NAME
            || (string) ($policy->policy_version ?? '') !== self::POLICY_VERSION
            || (string) ($policy->selection_procedure_version ?? '') !== self::SELECTION_PROCEDURE_VERSION
            || (string) ($policy->core_policy_fingerprint ?? '') !== self::CORE_POLICY_FINGERPRINT
            || (int) ($policy->instrument_id ?? 0) !== self::INSTRUMENT_ID
            || (string) ($policy->isin ?? '') !== self::ISIN
            || (string) ($policy->provider_symbol ?? '') !== self::PROVIDER_SYMBOL
            || $status !== 'active_recommendation'
            || ! self::databaseBoolean($policy->is_active ?? false)
            || ! self::databaseBoolean($policy->recommendation_only ?? false)
            || self::databaseBoolean($policy->automatic_execution ?? true)
            || self::databaseBoolean($policy->release_gate_passed ?? true)
            || ! self::databaseBoolean($policy->user_override ?? false)
            || (string) ($policy->activation_basis ?? '') !== 'explicit_user_override'
            || (string) ($policy->comparison_baseline ?? '') !== 'dynamic_tcn_observable'
            || (string) ($policy->signal_timing ?? '') !== 'after_xetra_close'
            || (string) ($policy->execution_timing ?? '') !== self::EXECUTION_TIME
            || (string) ($policy->formula ?? '') !== self::FORMULA
            || ($policy->maximum_holding_sessions ?? null) !== null) {
            throw new RuntimeException('The installed PUMA exit policy does not match its active contract.');
        }

        $configuration = $policy->configuration ?? null;
        if (is_string($configuration)) {
            $configuration = json_decode($configuration, true, 512, JSON_THROW_ON_ERROR);
        } elseif (is_object($configuration)) {
            $configuration = (array) $configuration;
        }
        if (! is_array($configuration)
            || self::canonicalize($configuration['policy_contract'] ?? null)
                !== self::canonicalize(self::policyContract())) {
            throw new RuntimeException('The installed PUMA policy payload does not match its fingerprint.');
        }

        $evidence = $policy->evidence ?? null;
        if (is_string($evidence)) {
            $evidence = json_decode($evidence, true, 512, JSON_THROW_ON_ERROR);
        } elseif (is_object($evidence)) {
            $evidence = (array) $evidence;
        }
        $runtimeBundle = is_array($evidence)
            ? ($evidence['active_runtime_bundle'] ?? null)
            : null;
        if (! is_array($runtimeBundle)
            || ($runtimeBundle['source_path'] ?? null) !== self::BUNDLE_SOURCE_PATH
            || ($runtimeBundle['manifest_sha256'] ?? null) !== self::BUNDLE_MANIFEST_SHA256
            || ($runtimeBundle['policy_json_sha256'] ?? null) !== self::BUNDLE_POLICY_JSON_SHA256
            || ($evidence['source_model_release_gates_passed'] ?? null) !== false
            || ($evidence['policy_release_gate_passed'] ?? null) !== false) {
            throw new RuntimeException('The installed PUMA policy evidence does not match the reviewed runtime bundle.');
        }
    }

    private static function observationInsert(array $input, string $inputHash, array $derived): array
    {
        return [
            'policy_name' => self::POLICY_NAME,
            'input_schema_version' => self::INPUT_SCHEMA_VERSION,
            'instrument_id' => self::INSTRUMENT_ID,
            'market_session_date' => $input['market_session_date'],
            'market_session_position' => $input['market_session_position'],
            'signal_as_of' => $input['signal_as_of'],
            'valid_until' => $input['valid_until'],
            'observation_role' => $input['observation_role'],
            'state_only' => $input['state_only'],
            'short_shock_10t' => $input['short_shock_10t'],
            'short_shock_10t_score' => $input['short_shock_10t_score'],
            'short_lower_tail_20t' => $input['short_lower_tail_20t'],
            'short_lower_tail_20t_score' => $input['short_lower_tail_20t_score'],
            'short_bear_area_40t' => $input['short_bear_area_40t'],
            'short_bear_area_40t_score' => $input['short_bear_area_40t_score'],
            'short_vote_count' => $derived['short_vote_count'],
            'champion_10t' => $input['champion_10t'],
            'champion_40t' => $input['champion_40t'],
            'long_curve_raw' => $derived['long_curve_raw'],
            'long_curve_confirmed' => $derived['long_curve_confirmed'],
            'trend_momentum_probability' => $input['trend_momentum_probability'],
            'indicator_raw' => $derived['indicator_raw'],
            'indicator_confirmed' => $derived['indicator_confirmed'],
            'exit_recommended' => $derived['exit_recommended'],
            'decision' => $derived['decision'],
            'execution_timing' => self::EXECUTION_TIME,
            'automatic_execution' => false,
            'input_sha256' => $inputHash,
            'source_lineage' => json_encode(
                $input['source_lineage'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ),
        ];
    }

    private static function observationToArray(stdClass $row): array
    {
        $result = (array) $row;
        foreach ([
            'short_shock_10t',
            'short_lower_tail_20t',
            'short_bear_area_40t',
            'long_curve_raw',
            'long_curve_confirmed',
            'indicator_raw',
            'indicator_confirmed',
            'exit_recommended',
            'automatic_execution',
            'state_only',
        ] as $booleanColumn) {
            if (array_key_exists($booleanColumn, $result)) {
                $result[$booleanColumn] = self::databaseBoolean($result[$booleanColumn]);
            }
        }

        foreach (['id', 'instrument_id', 'market_session_position', 'short_vote_count'] as $integerColumn) {
            if (array_key_exists($integerColumn, $result)) {
                $result[$integerColumn] = (int) $result[$integerColumn];
            }
        }

        if (isset($result['source_lineage']) && is_string($result['source_lineage'])) {
            $result['source_lineage'] = json_decode(
                $result['source_lineage'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        return $result;
    }

    private static function requireExact(array $payload, string $key, string|int $expected): void
    {
        if (! array_key_exists($key, $payload) || $payload[$key] !== $expected) {
            throw new InvalidArgumentException("{$key} must equal {$expected}.");
        }
    }

    private static function requiredBoolean(array $payload, string $key): bool
    {
        if (! array_key_exists($key, $payload) || ! is_bool($payload[$key])) {
            throw new InvalidArgumentException("{$key} must be a JSON boolean.");
        }

        return $payload[$key];
    }

    private static function requiredFiniteNumber(array $payload, string $key): float
    {
        $value = $payload[$key] ?? null;
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException("{$key} must be a finite JSON number.");
        }

        return (float) $value;
    }

    private static function requiredProbability(array $payload, string $key): float
    {
        $value = self::requiredFiniteNumber($payload, $key);
        if ($value < 0 || $value > 1) {
            throw new InvalidArgumentException("{$key} must be between 0 and 1.");
        }

        return $value;
    }

    private static function marketDate(mixed $value): string
    {
        if (! is_string($value)
            || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new InvalidArgumentException('market_session_date must be a valid YYYY-MM-DD date.');
        }

        return $value;
    }

    private static function timingContract(array $payload, string $marketDate): array
    {
        $signalAsOf = self::timestamp($payload['signal_as_of'] ?? null, 'signal_as_of');
        $validUntil = self::timestamp($payload['valid_until'] ?? null, 'valid_until');
        $role = $payload['observation_role'] ?? null;
        if (! is_string($role)
            || ! in_array($role, ['state_seed', 'historical_backfill', 'current_recommendation'], true)) {
            throw new InvalidArgumentException(
                'observation_role must be state_seed, historical_backfill, or current_recommendation.'
            );
        }
        $stateOnly = self::requiredBoolean($payload, 'state_only');

        $marketClose = CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $marketDate.' 17:30:00',
            'Europe/Berlin',
        );
        if ($marketClose === false || $signalAsOf->lessThan($marketClose)) {
            throw new InvalidArgumentException(
                'signal_as_of must not precede the completed market session close.'
            );
        }

        $expectedValidUntil = self::nextXetraOpen($marketDate);
        if (! $validUntil->equalTo($expectedValidUntil)) {
            throw new InvalidArgumentException(
                'valid_until must be the next regular 09:00 Europe/Berlin Xetra open.'
            );
        }

        if ($role === 'current_recommendation') {
            if ($stateOnly || ! $signalAsOf->lessThan($validUntil)) {
                throw new InvalidArgumentException(
                    'A current_recommendation must have state_only=false and signal_as_of < valid_until.'
                );
            }
        } elseif (! $stateOnly) {
            throw new InvalidArgumentException(
                'state_seed and historical_backfill observations must have state_only=true.'
            );
        }

        return [
            $signalAsOf->utc()->format('Y-m-d\TH:i:s.u\Z'),
            $validUntil->utc()->format('Y-m-d\TH:i:s.u\Z'),
            $role,
            $stateOnly,
        ];
    }

    private static function nextXetraOpen(string $marketDate): CarbonImmutable
    {
        $session = CarbonImmutable::createFromFormat('!Y-m-d', $marketDate, 'Europe/Berlin');
        if ($session === false) {
            throw new InvalidArgumentException('market_session_date cannot be placed on the Xetra calendar.');
        }

        $candidate = $session->addDay();
        while (! self::isXetraSession($candidate)) {
            $candidate = $candidate->addDay();
        }

        return $candidate->setTime(9, 0);
    }

    private static function isXetraSession(CarbonImmutable $day): bool
    {
        if ($day->isWeekend()) {
            return false;
        }

        $year = $day->year;
        $easter = self::easterSunday($year);
        $closedDates = [
            "{$year}-01-01",
            $easter->subDays(2)->toDateString(),
            $easter->addDay()->toDateString(),
            "{$year}-05-01",
            "{$year}-12-24",
            "{$year}-12-25",
            "{$year}-12-26",
            "{$year}-12-31",
        ];

        return ! in_array($day->toDateString(), $closedDates, true);
    }

    /** Gregorian Easter Sunday, matching the Mac runner calendar contract. */
    private static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        [$b, $c] = [intdiv($year, 100), $year % 100];
        [$d, $e] = [intdiv($b, 4), $b % 4];
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        [$i, $k] = [intdiv($c, 4), $c % 4];
        $ell = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $ell, 451);
        $month = intdiv($h + $ell - 7 * $m + 114, 31);
        $day = (($h + $ell - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'Europe/Berlin');
    }

    private static function timestamp(mixed $value, string $key): CarbonImmutable
    {
        if (! is_string($value)
            || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/D', $value) !== 1) {
            throw new InvalidArgumentException("{$key} must be an ISO-8601 timestamp with timezone.");
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException("{$key} is not a valid timestamp.", 0, $exception);
        }
    }

    private static function databaseTimestamp(mixed $value, string $key): CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value)) {
            throw new RuntimeException("The stored {$key} timestamp is missing.");
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable $exception) {
            throw new RuntimeException("The stored {$key} timestamp is invalid.", 0, $exception);
        }
    }

    private static function validateScorerDiagnostics(mixed $diagnostics): void
    {
        if (! is_array($diagnostics) || $diagnostics === [] || array_is_list($diagnostics)) {
            throw new InvalidArgumentException('scorer_diagnostics must be a non-empty JSON object.');
        }
        if (($diagnostics['automatic_execution'] ?? null) !== false
            || ! array_key_exists('maximum_holding_sessions', $diagnostics)
            || $diagnostics['maximum_holding_sessions'] !== null) {
            throw new InvalidArgumentException(
                'scorer_diagnostics must explicitly disable automatic execution and fixed holding horizons.'
            );
        }
    }

    private static function validateSourceLineage(array $lineage): void
    {
        if (($lineage['bundle_manifest_sha256'] ?? null) !== self::BUNDLE_MANIFEST_SHA256
            || ($lineage['frozen_replay_verified'] ?? null) !== true
            || ($lineage['source_release_gate_passed'] ?? null) !== false
            || ($lineage['activation'] ?? null) !== 'explicit_user_override'
            || ($lineage['comparison_baseline'] ?? null) !== 'dynamic_tcn_observable') {
            throw new InvalidArgumentException(
                'source_lineage does not match the reviewed PUMA runtime bundle and activation contract.'
            );
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }

    private static function databaseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 't' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'f' || $value === 'false' || $value === null) {
            return false;
        }

        throw new RuntimeException('Unexpected database boolean representation.');
    }
}
