<?php

namespace Tests\Unit;

use App\Services\PumaExitRecommendationService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PumaExitRecommendationServiceTest extends TestCase
{
    public function test_long_curve_branch_requires_two_consecutive_sessions_and_two_short_votes(): void
    {
        $first = $this->signals(position: 100, champion10: -0.02, champion40: -0.06);
        $firstResult = PumaExitRecommendationService::evaluateSignals($first, null);

        $this->assertTrue($firstResult['long_curve_raw']);
        $this->assertFalse($firstResult['long_curve_confirmed']);
        $this->assertFalse($firstResult['exit_recommended']);

        $second = $this->signals(position: 101, champion10: -0.01, champion40: -0.05);
        $secondResult = PumaExitRecommendationService::evaluateSignals($second, [
            'market_session_position' => 100,
            'long_curve_raw' => $firstResult['long_curve_raw'],
            'indicator_raw' => $firstResult['indicator_raw'],
        ]);

        $this->assertSame(2, $secondResult['short_vote_count']);
        $this->assertTrue($secondResult['long_curve_confirmed']);
        $this->assertTrue($secondResult['exit_recommended']);
        $this->assertSame('EXIT_RECOMMENDED', $secondResult['decision']);
    }

    public function test_long_curve_alone_does_not_exit_without_two_current_short_votes(): void
    {
        $current = $this->signals(
            position: 101,
            champion10: -0.01,
            champion40: -0.05,
            shortShock: true,
            shortTail: false,
            shortArea: false,
        );
        $result = PumaExitRecommendationService::evaluateSignals($current, [
            'market_session_position' => 100,
            'long_curve_raw' => true,
            'indicator_raw' => false,
        ]);

        $this->assertTrue($result['long_curve_confirmed']);
        $this->assertSame(1, $result['short_vote_count']);
        $this->assertFalse($result['exit_recommended']);
    }

    public function test_probability_branch_exits_after_two_sessions_at_the_inclusive_p55_threshold(): void
    {
        $current = $this->signals(
            position: 401,
            champion10: 0.01,
            champion40: 0.02,
            probability: 0.55,
            shortShock: false,
            shortTail: false,
            shortArea: false,
        );
        $result = PumaExitRecommendationService::evaluateSignals($current, [
            'market_session_position' => 400,
            'long_curve_raw' => false,
            'indicator_raw' => true,
        ]);

        $this->assertTrue($result['indicator_raw']);
        $this->assertTrue($result['indicator_confirmed']);
        $this->assertTrue($result['exit_recommended']);
    }

    public function test_curve_threshold_is_inclusive_but_curve_ordering_is_strict(): void
    {
        $atThreshold = PumaExitRecommendationService::evaluateSignals(
            $this->signals(position: 1, champion10: -0.04, champion40: -0.05),
            null,
        );
        $equalChampions = PumaExitRecommendationService::evaluateSignals(
            $this->signals(position: 2, champion10: -0.05, champion40: -0.05),
            null,
        );

        $this->assertTrue($atThreshold['long_curve_raw']);
        $this->assertFalse($equalChampions['long_curve_raw']);
    }

    public function test_a_market_session_gap_resets_both_confirmations(): void
    {
        $current = $this->signals(
            position: 103,
            champion10: -0.01,
            champion40: -0.05,
            probability: 0.90,
        );
        $result = PumaExitRecommendationService::evaluateSignals($current, [
            'market_session_position' => 101,
            'long_curve_raw' => true,
            'indicator_raw' => true,
        ]);

        $this->assertTrue($result['long_curve_raw']);
        $this->assertTrue($result['indicator_raw']);
        $this->assertFalse($result['long_curve_confirmed']);
        $this->assertFalse($result['indicator_confirmed']);
        $this->assertFalse($result['exit_recommended']);
    }

    public function test_result_is_recommendation_only_and_has_no_fixed_holding_horizon(): void
    {
        $result = PumaExitRecommendationService::evaluateSignals(
            $this->signals(position: 1, champion10: 0.01, champion40: 0.02),
            null,
        );

        $this->assertFalse($result['automatic_execution']);
        $this->assertNull($result['maximum_holding_sessions']);
        $this->assertSame('next_xetra_adjusted_open', $result['execution_timing']);
    }

    public function test_laravel_policy_contract_matches_the_reviewed_core_fingerprint(): void
    {
        PumaExitRecommendationService::assertFrozenPolicyFingerprint();

        $contract = PumaExitRecommendationService::policyContract();
        $this->assertSame('(S_2 & L) | I_trend_momentum_probability_p55', $contract['formula']);
        $this->assertSame('XETRA', $contract['market_calendar']);
        $this->assertSame('next_xetra_adjusted_open', $contract['execution_time']);
        $this->assertNull($contract['maximum_holding_sessions']);
        $this->assertFalse($contract['entry_veto']);
    }

    public function test_payload_contract_is_canonical_and_hash_is_key_order_independent(): void
    {
        $first = $this->payload();
        $first['source_lineage']['z'] = ['b' => 2, 'a' => 1];
        $second = $first;
        $second['source_lineage'] = [
            'z' => ['a' => 1, 'b' => 2],
            'comparison_baseline' => 'dynamic_tcn_observable',
            'activation' => 'explicit_user_override',
            'source_release_gate_passed' => false,
            'frozen_replay_verified' => true,
            'bundle_manifest_sha256' => PumaExitRecommendationService::BUNDLE_MANIFEST_SHA256,
        ];

        $normalizedFirst = PumaExitRecommendationService::normalizePayload($first);
        $normalizedSecond = PumaExitRecommendationService::normalizePayload($second);

        $this->assertSame(
            PumaExitRecommendationService::inputHash($normalizedFirst),
            PumaExitRecommendationService::inputHash($normalizedSecond),
        );
        $this->assertSame('2026-09-04T15:31:00.000000Z', $normalizedFirst['signal_as_of']);
        $this->assertSame('2026-09-07T07:00:00.000000Z', $normalizedFirst['valid_until']);
        $this->assertSame(1, $normalizedFirst['schema_version']);
        $this->assertArrayNotHasKey('scorer_diagnostics', $normalizedFirst);
    }

    public function test_non_authoritative_flat_runner_diagnostics_do_not_change_the_observation_hash(): void
    {
        $first = $this->payload();
        $second = $first;
        $second['scorer_diagnostics']['exit_signal'] = true;
        $second['scorer_diagnostics']['reasons'] = ['changed_non_authoritative_diagnostic'];

        $this->assertSame(
            PumaExitRecommendationService::inputHash(
                PumaExitRecommendationService::normalizePayload($first)
            ),
            PumaExitRecommendationService::inputHash(
                PumaExitRecommendationService::normalizePayload($second)
            ),
        );
    }

    public function test_flat_runner_payload_schema_is_accepted_without_nested_model_objects(): void
    {
        $payload = $this->payload();
        $payload['source_lineage']['source_data_sha256'] = str_repeat('a', 64);
        $payload['source_lineage']['source_data_as_of'] = [
            'pum' => '2026-09-04',
            'home_index' => '2026-09-04',
            'dgs2' => '2026-09-03',
            'vix' => '2026-09-03',
        ];
        $payload['source_lineage']['artifact_sha256'] = [
            'shock_hurdle_rf_10t' => str_repeat('b', 64),
        ];

        $normalized = PumaExitRecommendationService::normalizePayload($payload);

        $this->assertSame([
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
        ], array_keys($normalized));
        $this->assertArrayNotHasKey('models', $normalized);
        $this->assertArrayNotHasKey('scorer_diagnostics', $normalized);
    }

    public function test_flat_runner_payload_rejects_unknown_top_level_schema_drift(): void
    {
        $payload = $this->payload();
        $payload['models'] = ['nested' => true];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported top-level fields: models');
        PumaExitRecommendationService::normalizePayload($payload);
    }

    public function test_payload_rejects_any_other_instrument_identity(): void
    {
        $payload = $this->payload();
        $payload['instrument_id'] = 1938;

        $this->expectException(InvalidArgumentException::class);
        PumaExitRecommendationService::normalizePayload($payload);
    }

    public function test_payload_rejects_a_signal_before_the_xetra_close(): void
    {
        $payload = $this->payload();
        $payload['signal_as_of'] = '2026-09-04T17:29:59+02:00';

        $this->expectException(InvalidArgumentException::class);
        PumaExitRecommendationService::normalizePayload($payload);
    }

    public function test_historical_backfill_may_be_generated_after_valid_until_but_is_state_only(): void
    {
        $payload = $this->payload();
        $payload['signal_as_of'] = '2026-09-08T12:00:00+02:00';
        $payload['observation_role'] = 'historical_backfill';
        $payload['state_only'] = true;

        $normalized = PumaExitRecommendationService::normalizePayload($payload);

        $this->assertSame('historical_backfill', $normalized['observation_role']);
        $this->assertTrue($normalized['state_only']);
    }

    public function test_current_recommendation_must_be_generated_before_its_exclusive_valid_until(): void
    {
        $payload = $this->payload();
        $payload['signal_as_of'] = $payload['valid_until'];

        $this->expectException(InvalidArgumentException::class);
        PumaExitRecommendationService::normalizePayload($payload);
    }

    public function test_valid_until_must_match_the_next_regular_xetra_open_across_holidays(): void
    {
        $payload = $this->payload();
        $payload['market_session_date'] = '2026-12-23';
        $payload['signal_as_of'] = '2026-12-23T17:31:00+01:00';
        $payload['valid_until'] = '2026-12-28T09:00:00+01:00';

        $normalized = PumaExitRecommendationService::normalizePayload($payload);

        $this->assertSame('2026-12-28T08:00:00.000000Z', $normalized['valid_until']);

        $easterPayload = $this->payload();
        $easterPayload['market_session_date'] = '2027-03-25';
        $easterPayload['signal_as_of'] = '2027-03-25T17:31:00+01:00';
        $easterPayload['valid_until'] = '2027-03-30T09:00:00+02:00';
        $this->assertSame(
            '2027-03-30T07:00:00.000000Z',
            PumaExitRecommendationService::normalizePayload($easterPayload)['valid_until'],
        );

        $payload['valid_until'] = '2026-12-29T09:00:00+01:00';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('next regular 09:00 Europe/Berlin Xetra open');
        PumaExitRecommendationService::normalizePayload($payload);
    }

    public function test_freshness_guard_hides_state_only_and_expired_exit_signals(): void
    {
        $stateOnly = PumaExitRecommendationService::applyFreshnessGuard([
            'observation_id' => 1,
            'observation_role' => 'historical_backfill',
            'state_only' => true,
            'decision' => 'EXIT_RECOMMENDED',
            'exit_recommended' => true,
            'signal_as_of' => '2026-09-08T10:00:00Z',
            'valid_until' => '2026-09-07T07:00:00Z',
        ], CarbonImmutable::parse('2026-09-08T10:01:00Z'));
        $expired = PumaExitRecommendationService::applyFreshnessGuard([
            'observation_id' => 2,
            'observation_role' => 'current_recommendation',
            'state_only' => false,
            'decision' => 'EXIT_RECOMMENDED',
            'exit_recommended' => true,
            'signal_as_of' => '2026-09-06T10:00:00Z',
            'valid_until' => '2026-09-07T07:00:00Z',
        ], CarbonImmutable::parse('2026-09-07T07:00:00Z'));
        $expiredHold = PumaExitRecommendationService::applyFreshnessGuard([
            'observation_id' => 3,
            'observation_role' => 'current_recommendation',
            'state_only' => false,
            'decision' => 'HOLD',
            'exit_recommended' => false,
            'signal_as_of' => '2026-09-06T10:00:00Z',
            'valid_until' => '2026-09-07T07:00:00Z',
        ], CarbonImmutable::parse('2026-09-07T07:00:01Z'));

        $this->assertSame('STATE_ONLY', $stateOnly['recommendation_status']);
        $this->assertSame('NO_DATA', $stateOnly['decision']);
        $this->assertFalse($stateOnly['exit_recommended']);
        $this->assertFalse($stateOnly['recommendation_actionable']);
        $this->assertSame('EXIT_RECOMMENDED', $stateOnly['model_decision']);
        $this->assertSame('EXPIRED', $expired['recommendation_status']);
        $this->assertSame('NO_DATA', $expired['decision']);
        $this->assertFalse($expired['exit_recommended']);
        $this->assertSame('EXPIRED', $expiredHold['recommendation_status']);
        $this->assertSame('NO_DATA', $expiredHold['decision']);
    }

    public function test_freshness_guard_keeps_only_a_current_unexpired_decision_visible(): void
    {
        $current = PumaExitRecommendationService::applyFreshnessGuard([
            'observation_id' => 2,
            'observation_role' => 'current_recommendation',
            'state_only' => false,
            'decision' => 'EXIT_RECOMMENDED',
            'exit_recommended' => true,
            'signal_as_of' => '2026-09-06T10:00:00Z',
            'valid_until' => '2026-09-07T07:00:00Z',
        ], CarbonImmutable::parse('2026-09-07T06:59:59Z'));

        $this->assertSame('CURRENT', $current['recommendation_status']);
        $this->assertSame('EXIT_RECOMMENDED', $current['decision']);
        $this->assertTrue($current['exit_recommended']);
        $this->assertTrue($current['recommendation_actionable']);
    }

    private function signals(
        int $position,
        float $champion10,
        float $champion40,
        float $probability = 0.10,
        bool $shortShock = true,
        bool $shortTail = true,
        bool $shortArea = false,
    ): array {
        return [
            'market_session_position' => $position,
            'short_shock_10t' => $shortShock,
            'short_lower_tail_20t' => $shortTail,
            'short_bear_area_40t' => $shortArea,
            'champion_10t' => $champion10,
            'champion_40t' => $champion40,
            'trend_momentum_probability' => $probability,
        ];
    }

    private function payload(): array
    {
        return [
            'schema_version' => PumaExitRecommendationService::INPUT_SCHEMA_VERSION,
            'policy_name' => PumaExitRecommendationService::POLICY_NAME,
            'policy_version' => PumaExitRecommendationService::POLICY_VERSION,
            'core_policy_fingerprint' => PumaExitRecommendationService::CORE_POLICY_FINGERPRINT,
            'instrument_id' => PumaExitRecommendationService::INSTRUMENT_ID,
            'symbol' => PumaExitRecommendationService::SYMBOL,
            'isin' => PumaExitRecommendationService::ISIN,
            'provider_symbol' => PumaExitRecommendationService::PROVIDER_SYMBOL,
            'market_calendar' => PumaExitRecommendationService::MARKET_CALENDAR,
            'market_session_date' => '2026-09-04',
            'market_session_position' => 1234,
            'signal_as_of' => '2026-09-04T17:31:00+02:00',
            'valid_until' => '2026-09-07T09:00:00+02:00',
            'observation_role' => 'current_recommendation',
            'state_only' => false,
            'short_shock_10t' => true,
            'short_shock_10t_score' => -0.12,
            'short_lower_tail_20t' => true,
            'short_lower_tail_20t_score' => -0.08,
            'short_bear_area_40t' => false,
            'short_bear_area_40t_score' => -0.03,
            'champion_10t' => -0.02,
            'champion_40t' => -0.06,
            'trend_momentum_probability' => 0.40,
            'source_lineage' => [
                'bundle_manifest_sha256' => PumaExitRecommendationService::BUNDLE_MANIFEST_SHA256,
                'frozen_replay_verified' => true,
                'source_release_gate_passed' => false,
                'activation' => 'explicit_user_override',
                'comparison_baseline' => 'dynamic_tcn_observable',
            ],
            'scorer_diagnostics' => [
                'short_vote_count' => 2,
                'long_curve_raw' => true,
                'long_curve_confirmed' => false,
                'indicator_raw' => false,
                'indicator_confirmed' => false,
                'exit_signal' => false,
                'reasons' => ['hold'],
                'automatic_execution' => false,
                'maximum_holding_sessions' => null,
            ],
        ];
    }
}
