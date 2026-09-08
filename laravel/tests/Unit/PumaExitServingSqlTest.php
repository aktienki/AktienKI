<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PumaExitServingSqlTest extends TestCase
{
    private string $sql;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 2).'/database/serving/028_puma_exit_recommendation_policy.sql';
        $sql = file_get_contents($path);
        $this->assertNotFalse($sql);
        $this->sql = $sql;
    }

    public function test_policy_identity_version_and_fingerprint_are_pinned(): void
    {
        $this->assertStringContainsString('pum_short_curve_trend_p55_v2', $this->sql);
        $this->assertStringContainsString('pum-long-exit-s2-l-or-trend-p55-v1', $this->sql);
        $this->assertStringContainsString(
            'pum-short-indicator-long-exit-combination-search-v2',
            $this->sql,
        );
        $this->assertStringContainsString(
            'c216205e3f7d35f9752f60acac9d02a717339f11c537d47dbf384e6ee2905f79',
            $this->sql,
        );
        $this->assertStringContainsString(
            '59b6648d5a03fdafbd2e0233c584a49d723e48b16b617163b1373a233bf62f45',
            $this->sql,
        );
        $this->assertStringContainsString(
            'b4f47bfef6ba2554fc18131909380e96be07eb1311e55ed7f46c34ba60a9b0ff',
            $this->sql,
        );
        $this->assertStringContainsString(
            '/Users/aktienki/pipeline-data/model-releases/pum-long-exit/pum-long-exit-s2-l-or-trend-p55-v1',
            $this->sql,
        );
        $this->assertStringContainsString('instrument_id = 1085', $this->sql);
        $this->assertStringContainsString("isin = 'DE0006969603'", $this->sql);
        $this->assertStringContainsString("provider_symbol = 'PUM:XETR'", $this->sql);
    }

    public function test_policy_is_an_active_non_executing_override_without_a_fixed_horizon(): void
    {
        $this->assertStringContainsString("status = 'active_recommendation'", $this->sql);
        $this->assertStringContainsString('AND recommendation_only', $this->sql);
        $this->assertStringContainsString('AND NOT automatic_execution', $this->sql);
        $this->assertStringContainsString('AND NOT release_gate_passed', $this->sql);
        $this->assertStringContainsString('AND user_override', $this->sql);
        $this->assertStringContainsString("comparison_baseline = 'dynamic_tcn_observable'", $this->sql);
        $this->assertStringContainsString('maximum_holding_sessions IS NULL', $this->sql);
        $this->assertStringContainsString("execution_timing = 'next_xetra_adjusted_open'", $this->sql);
    }

    public function test_signal_history_is_unique_immutable_and_server_validated(): void
    {
        $this->assertStringContainsString(
            'UNIQUE (policy_name, market_session_date)',
            $this->sql,
        );
        $this->assertStringContainsString(
            'UNIQUE (policy_name, market_session_position)',
            $this->sql,
        );
        $this->assertStringContainsString('BEFORE UPDATE OR DELETE', $this->sql);
        $this->assertStringContainsString('validate_puma_exit_signal_observation', $this->sql);
        $this->assertStringContainsString('expected_short_votes >= 2 AND expected_long_confirmed', $this->sql);
        $this->assertStringContainsString('expected_indicator_confirmed', $this->sql);
        $this->assertStringContainsString('input_schema_version = 1', $this->sql);
        $this->assertStringContainsString("observation_role IN ('state_seed', 'historical_backfill', 'current_recommendation')", $this->sql);
        $this->assertStringContainsString('state_only', $this->sql);
        $this->assertStringContainsString('signal_as_of < valid_until', $this->sql);
        $this->assertStringContainsString('serving_puma_is_xetra_session', $this->sql);
        $this->assertStringContainsString(
            'valid_until = serving_puma_next_xetra_open(market_session_date)',
            $this->sql,
        );
    }

    public function test_sql_exposes_a_dedicated_current_recommendation_view(): void
    {
        $this->assertStringContainsString(
            'CREATE OR REPLACE VIEW serving_current_exit_recommendations',
            $this->sql,
        );
        $this->assertStringContainsString("COALESCE(observation.decision, 'NO_DATA') AS model_decision", $this->sql);
        $this->assertStringContainsString('CURRENT_TIMESTAMP < observation.valid_until', $this->sql);
        $this->assertStringContainsString("ELSE 'NO_DATA'", $this->sql);
        $this->assertStringContainsString('AS recommendation_actionable', $this->sql);
        $this->assertStringNotContainsString('normalized_score_exit', $this->sql);
        $this->assertStringNotContainsString('serving_paper_positions', $this->sql);
    }
}
