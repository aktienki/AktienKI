BEGIN;

-- Fail before creating anything when the serving identity does not match the
-- exact PUMA listing for which this policy was evaluated.
DO $identity$
DECLARE
    instrument_row serving_instruments%ROWTYPE;
BEGIN
    SELECT *
      INTO instrument_row
      FROM serving_instruments
     WHERE id = 1085;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'PUMA exit policy requires serving instrument 1085';
    END IF;

    IF instrument_row.isin IS DISTINCT FROM 'DE0006969603'
       OR instrument_row.provider_symbol IS DISTINCT FROM 'PUM:XETR' THEN
        RAISE EXCEPTION
            'PUMA identity mismatch for instrument 1085 (isin %, provider_symbol %)',
            instrument_row.isin,
            instrument_row.provider_symbol;
    END IF;
END
$identity$;

CREATE OR REPLACE FUNCTION serving_puma_exit_policy_contract()
RETURNS jsonb
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
AS $contract_function$
    SELECT $contract_json$
    {
      "policy_version": "pum-long-exit-s2-l-or-trend-p55-v1",
      "selection_procedure_version": "pum-short-indicator-long-exit-combination-search-v2",
      "formula": "(S_2 & L) | I_trend_momentum_probability_p55",
      "symbol": "PUM",
      "provider_symbol": "PUM:XETR",
      "isin": "DE0006969603",
      "market_calendar": "XETRA",
      "short_model_names": ["shock_hurdle_rf_10t", "lower_tail_hgb_20t", "bear_area_tcn_40t"],
      "minimum_short_votes": 2,
      "long_curve_threshold": -0.05,
      "long_curve_confirmation_sessions": 2,
      "indicator_model_name": "trend_momentum_probability",
      "indicator_probability_threshold": 0.55,
      "indicator_confirmation_sessions": 2,
      "signal_time": "after_xetra_close",
      "execution_time": "next_xetra_adjusted_open",
      "maximum_holding_sessions": null,
      "entry_veto": false
    }
    $contract_json$::jsonb
$contract_function$;

CREATE TABLE IF NOT EXISTS serving_exit_policies (
    policy_name                 text PRIMARY KEY,
    policy_version              text NOT NULL,
    selection_procedure_version text NOT NULL,
    core_policy_fingerprint     text NOT NULL
        CHECK (core_policy_fingerprint ~ '^[0-9a-f]{64}$'),
    instrument_id               bigint NOT NULL REFERENCES serving_instruments(id),
    isin                        text NOT NULL,
    provider_symbol             text NOT NULL,
    status                      text NOT NULL,
    is_active                   boolean NOT NULL DEFAULT false,
    recommendation_only         boolean NOT NULL DEFAULT true,
    automatic_execution         boolean NOT NULL DEFAULT false,
    release_gate_passed         boolean NOT NULL DEFAULT false,
    user_override               boolean NOT NULL DEFAULT false,
    activation_basis            text NOT NULL,
    comparison_baseline         text NOT NULL,
    signal_timing               text NOT NULL,
    execution_timing            text NOT NULL,
    maximum_holding_sessions    integer,
    formula                     text NOT NULL,
    configuration               jsonb NOT NULL,
    evidence                    jsonb NOT NULL,
    activated_at                timestamptz,
    created_at                  timestamptz NOT NULL DEFAULT now(),
    updated_at                  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT serving_exit_policies_puma_v2_scope_check CHECK (
        policy_name = 'pum_short_curve_trend_p55_v2'
        AND (
            policy_version = 'pum-long-exit-s2-l-or-trend-p55-v1'
            AND selection_procedure_version = 'pum-short-indicator-long-exit-combination-search-v2'
            AND core_policy_fingerprint = 'c216205e3f7d35f9752f60acac9d02a717339f11c537d47dbf384e6ee2905f79'
            AND instrument_id = 1085
            AND isin = 'DE0006969603'
            AND provider_symbol = 'PUM:XETR'
            AND status IN ('active_recommendation', 'disabled')
            AND (
                (is_active AND status = 'active_recommendation')
                OR (NOT is_active AND status = 'disabled')
            )
            AND recommendation_only
            AND NOT automatic_execution
            AND NOT release_gate_passed
            AND user_override
            AND activation_basis = 'explicit_user_override'
            AND comparison_baseline = 'dynamic_tcn_observable'
            AND signal_timing = 'after_xetra_close'
            AND execution_timing = 'next_xetra_adjusted_open'
            AND maximum_holding_sessions IS NULL
            AND formula = '(S_2 & L) | I_trend_momentum_probability_p55'
            AND configuration->'policy_contract'
                IS NOT DISTINCT FROM serving_puma_exit_policy_contract()
        )
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS serving_exit_policies_one_active_per_instrument_idx
    ON serving_exit_policies (instrument_id)
    WHERE is_active;

CREATE OR REPLACE FUNCTION serving_puma_is_xetra_session(candidate date)
RETURNS boolean
LANGUAGE plpgsql
IMMUTABLE
PARALLEL SAFE
AS $xetra_session$
DECLARE
    year_number integer := EXTRACT(YEAR FROM candidate)::integer;
    a integer;
    b integer;
    c integer;
    d integer;
    e integer;
    f integer;
    g integer;
    h integer;
    i integer;
    k integer;
    ell integer;
    m integer;
    easter_month integer;
    easter_day integer;
    easter_sunday date;
BEGIN
    IF EXTRACT(ISODOW FROM candidate) IN (6, 7) THEN
        RETURN false;
    END IF;

    a := year_number % 19;
    b := year_number / 100;
    c := year_number % 100;
    d := b / 4;
    e := b % 4;
    f := (b + 8) / 25;
    g := (b - f + 1) / 3;
    h := (19 * a + b - d - g + 15) % 30;
    i := c / 4;
    k := c % 4;
    ell := (32 + 2 * e + 2 * i - h - k) % 7;
    m := (a + 11 * h + 22 * ell) / 451;
    easter_month := (h + ell - 7 * m + 114) / 31;
    easter_day := ((h + ell - 7 * m + 114) % 31) + 1;
    easter_sunday := make_date(year_number, easter_month, easter_day);

    RETURN candidate NOT IN (
        make_date(year_number, 1, 1),
        easter_sunday - 2,
        easter_sunday + 1,
        make_date(year_number, 5, 1),
        make_date(year_number, 12, 24),
        make_date(year_number, 12, 25),
        make_date(year_number, 12, 26),
        make_date(year_number, 12, 31)
    );
END
$xetra_session$;

CREATE OR REPLACE FUNCTION serving_puma_next_xetra_open(session_date date)
RETURNS timestamptz
LANGUAGE plpgsql
IMMUTABLE
PARALLEL SAFE
AS $next_xetra_open$
DECLARE
    candidate date := session_date + 1;
BEGIN
    WHILE NOT serving_puma_is_xetra_session(candidate) LOOP
        candidate := candidate + 1;
    END LOOP;

    RETURN (candidate + TIME '09:00:00') AT TIME ZONE 'Europe/Berlin';
END
$next_xetra_open$;

CREATE TABLE IF NOT EXISTS serving_exit_signal_observations (
    id                          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    policy_name                 text NOT NULL REFERENCES serving_exit_policies(policy_name),
    input_schema_version        smallint NOT NULL CHECK (input_schema_version = 1),
    instrument_id               bigint NOT NULL REFERENCES serving_instruments(id),
    market_session_date         date NOT NULL,
    market_session_position     bigint NOT NULL CHECK (market_session_position >= 0),
    signal_as_of                timestamptz NOT NULL,
    valid_until                 timestamptz NOT NULL,
    observation_role            text NOT NULL CHECK (
        observation_role IN ('state_seed', 'historical_backfill', 'current_recommendation')
    ),
    state_only                  boolean NOT NULL,
    short_shock_10t             boolean NOT NULL,
    short_shock_10t_score       double precision NOT NULL
        CHECK (short_shock_10t_score::text NOT IN ('NaN', 'Infinity', '-Infinity')),
    short_lower_tail_20t        boolean NOT NULL,
    short_lower_tail_20t_score  double precision NOT NULL
        CHECK (short_lower_tail_20t_score::text NOT IN ('NaN', 'Infinity', '-Infinity')),
    short_bear_area_40t         boolean NOT NULL,
    short_bear_area_40t_score   double precision NOT NULL
        CHECK (short_bear_area_40t_score::text NOT IN ('NaN', 'Infinity', '-Infinity')),
    short_vote_count            smallint NOT NULL CHECK (short_vote_count BETWEEN 0 AND 3),
    champion_10t                double precision NOT NULL
        CHECK (champion_10t::text NOT IN ('NaN', 'Infinity', '-Infinity')),
    champion_40t                double precision NOT NULL
        CHECK (champion_40t::text NOT IN ('NaN', 'Infinity', '-Infinity')),
    long_curve_raw              boolean NOT NULL,
    long_curve_confirmed        boolean NOT NULL,
    trend_momentum_probability  double precision NOT NULL
        CHECK (trend_momentum_probability BETWEEN 0 AND 1),
    indicator_raw               boolean NOT NULL,
    indicator_confirmed         boolean NOT NULL,
    exit_recommended            boolean NOT NULL,
    decision                    text NOT NULL CHECK (decision IN ('HOLD', 'EXIT_RECOMMENDED')),
    execution_timing            text NOT NULL DEFAULT 'next_xetra_adjusted_open'
        CHECK (execution_timing = 'next_xetra_adjusted_open'),
    automatic_execution         boolean NOT NULL DEFAULT false
        CHECK (NOT automatic_execution),
    input_sha256                text NOT NULL CHECK (input_sha256 ~ '^[0-9a-f]{64}$'),
    source_lineage              jsonb NOT NULL CHECK (
        jsonb_typeof(source_lineage) = 'object' AND source_lineage <> '{}'::jsonb
    ),
    imported_at                 timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT serving_exit_signal_observations_puma_only_check CHECK (
        policy_name = 'pum_short_curve_trend_p55_v2' AND instrument_id = 1085
    ),
    CONSTRAINT serving_exit_signal_observations_post_close_check CHECK (
        signal_as_of >= (
            (market_session_date + TIME '17:30:00') AT TIME ZONE 'Europe/Berlin'
        )
        AND valid_until = serving_puma_next_xetra_open(market_session_date)
    ),
    CONSTRAINT serving_exit_signal_observations_role_check CHECK (
        (
            state_only
            AND observation_role IN ('state_seed', 'historical_backfill')
        )
        OR (
            NOT state_only
            AND observation_role = 'current_recommendation'
            AND signal_as_of < valid_until
        )
    ),
    CONSTRAINT serving_exit_signal_observations_lineage_check CHECK (
        source_lineage ?& ARRAY[
            'bundle_manifest_sha256',
            'frozen_replay_verified',
            'source_release_gate_passed',
            'activation',
            'comparison_baseline'
        ]
        AND source_lineage->>'bundle_manifest_sha256'
            = '59b6648d5a03fdafbd2e0233c584a49d723e48b16b617163b1373a233bf62f45'
        AND source_lineage->'frozen_replay_verified' = 'true'::jsonb
        AND source_lineage->'source_release_gate_passed' = 'false'::jsonb
        AND source_lineage->>'activation' = 'explicit_user_override'
        AND source_lineage->>'comparison_baseline' = 'dynamic_tcn_observable'
    ),
    CONSTRAINT serving_exit_signal_observations_date_unique
        UNIQUE (policy_name, market_session_date),
    CONSTRAINT serving_exit_signal_observations_position_unique
        UNIQUE (policy_name, market_session_position)
);

CREATE INDEX IF NOT EXISTS serving_exit_signal_observations_current_idx
    ON serving_exit_signal_observations
        (policy_name, market_session_position DESC, imported_at DESC);

COMMENT ON COLUMN serving_exit_signal_observations.valid_until IS
    'Exclusive freshness cutoff at the next regular Xetra open; never a position holding horizon.';
COMMENT ON COLUMN serving_exit_signal_observations.state_only IS
    'True for seed/backfill rows that build causal confirmation state but can never be an actionable recommendation.';

CREATE OR REPLACE FUNCTION validate_puma_exit_signal_observation()
RETURNS trigger
LANGUAGE plpgsql
AS $validation$
DECLARE
    policy_row serving_exit_policies%ROWTYPE;
    previous_row serving_exit_signal_observations%ROWTYPE;
    expected_short_votes smallint;
    expected_long_raw boolean;
    expected_indicator_raw boolean;
    expected_long_confirmed boolean;
    expected_indicator_confirmed boolean;
    expected_exit boolean;
    consecutive_session boolean;
BEGIN
    PERFORM pg_advisory_xact_lock(
        hashtext('serving-exit-observation'),
        hashtext(NEW.policy_name)
    );

    SELECT *
      INTO STRICT policy_row
      FROM serving_exit_policies
     WHERE policy_name = NEW.policy_name;

    IF NOT policy_row.is_active
       OR NOT policy_row.recommendation_only
       OR policy_row.automatic_execution THEN
        RAISE EXCEPTION 'Exit policy % is not an active recommendation-only policy', NEW.policy_name;
    END IF;

    IF NEW.instrument_id <> policy_row.instrument_id THEN
        RAISE EXCEPTION 'Observation instrument % does not match policy instrument %',
            NEW.instrument_id,
            policy_row.instrument_id;
    END IF;

    SELECT *
      INTO previous_row
      FROM serving_exit_signal_observations
     WHERE policy_name = NEW.policy_name
     ORDER BY market_session_position DESC
     LIMIT 1;

    IF FOUND AND NEW.market_session_position <= previous_row.market_session_position THEN
        RAISE EXCEPTION 'Exit observations must be appended in market-session order';
    END IF;

    IF FOUND AND NEW.market_session_date <= previous_row.market_session_date THEN
        RAISE EXCEPTION 'Exit observation dates must increase';
    END IF;

    consecutive_session := FOUND
        AND NEW.market_session_position = previous_row.market_session_position + 1;
    expected_short_votes := NEW.short_shock_10t::integer
        + NEW.short_lower_tail_20t::integer
        + NEW.short_bear_area_40t::integer;
    expected_long_raw := NEW.champion_40t <= -0.05
        AND NEW.champion_40t < NEW.champion_10t;
    expected_indicator_raw := NEW.trend_momentum_probability >= 0.55;
    expected_long_confirmed := expected_long_raw
        AND consecutive_session
        AND previous_row.long_curve_raw;
    expected_indicator_confirmed := expected_indicator_raw
        AND consecutive_session
        AND previous_row.indicator_raw;
    expected_exit := (expected_short_votes >= 2 AND expected_long_confirmed)
        OR expected_indicator_confirmed;

    IF NEW.short_vote_count IS DISTINCT FROM expected_short_votes
       OR NEW.long_curve_raw IS DISTINCT FROM expected_long_raw
       OR NEW.indicator_raw IS DISTINCT FROM expected_indicator_raw
       OR NEW.long_curve_confirmed IS DISTINCT FROM expected_long_confirmed
       OR NEW.indicator_confirmed IS DISTINCT FROM expected_indicator_confirmed
       OR NEW.exit_recommended IS DISTINCT FROM expected_exit
       OR NEW.decision IS DISTINCT FROM CASE WHEN expected_exit THEN 'EXIT_RECOMMENDED' ELSE 'HOLD' END THEN
        RAISE EXCEPTION 'Derived PUMA exit fields do not match policy %', NEW.policy_name;
    END IF;

    RETURN NEW;
END
$validation$;

DROP TRIGGER IF EXISTS serving_exit_signal_observations_validate_insert
    ON serving_exit_signal_observations;
CREATE TRIGGER serving_exit_signal_observations_validate_insert
    BEFORE INSERT ON serving_exit_signal_observations
    FOR EACH ROW EXECUTE FUNCTION validate_puma_exit_signal_observation();

CREATE OR REPLACE FUNCTION reject_exit_signal_observation_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $immutable$
BEGIN
    RAISE EXCEPTION
        'Serving exit observations are immutable; append a new market session instead';
END
$immutable$;

DROP TRIGGER IF EXISTS serving_exit_signal_observations_immutable
    ON serving_exit_signal_observations;
CREATE TRIGGER serving_exit_signal_observations_immutable
    BEFORE UPDATE OR DELETE ON serving_exit_signal_observations
    FOR EACH ROW EXECUTE FUNCTION reject_exit_signal_observation_mutation();

INSERT INTO serving_exit_policies (
    policy_name,
    policy_version,
    selection_procedure_version,
    core_policy_fingerprint,
    instrument_id,
    isin,
    provider_symbol,
    status,
    is_active,
    recommendation_only,
    automatic_execution,
    release_gate_passed,
    user_override,
    activation_basis,
    comparison_baseline,
    signal_timing,
    execution_timing,
    maximum_holding_sessions,
    formula,
    configuration,
    evidence,
    activated_at
) VALUES (
    'pum_short_curve_trend_p55_v2',
    'pum-long-exit-s2-l-or-trend-p55-v1',
    'pum-short-indicator-long-exit-combination-search-v2',
    'c216205e3f7d35f9752f60acac9d02a717339f11c537d47dbf384e6ee2905f79',
    1085,
    'DE0006969603',
    'PUM:XETR',
    'active_recommendation',
    true,
    true,
    false,
    false,
    true,
    'explicit_user_override',
    'dynamic_tcn_observable',
    'after_xetra_close',
    'next_xetra_adjusted_open',
    NULL,
    '(S_2 & L) | I_trend_momentum_probability_p55',
    $configuration$
    {
      "policy_contract": {
        "policy_version": "pum-long-exit-s2-l-or-trend-p55-v1",
        "selection_procedure_version": "pum-short-indicator-long-exit-combination-search-v2",
        "formula": "(S_2 & L) | I_trend_momentum_probability_p55",
        "symbol": "PUM",
        "provider_symbol": "PUM:XETR",
        "isin": "DE0006969603",
        "market_calendar": "XETRA",
        "short_model_names": ["shock_hurdle_rf_10t", "lower_tail_hgb_20t", "bear_area_tcn_40t"],
        "minimum_short_votes": 2,
        "long_curve_threshold": -0.05,
        "long_curve_confirmation_sessions": 2,
        "indicator_model_name": "trend_momentum_probability",
        "indicator_probability_threshold": 0.55,
        "indicator_confirmation_sessions": 2,
        "signal_time": "after_xetra_close",
        "execution_time": "next_xetra_adjusted_open",
        "maximum_holding_sessions": null,
        "entry_veto": false
      },
      "exit_has_fixed_horizon": false,
      "short_vote_minimum": 2,
      "long_curve": {
        "champion_40t_maximum": -0.05,
        "requires_champion_40t_below_champion_10t": true,
        "confirmation_xetra_sessions": 2
      },
      "trend_momentum_probability": {
        "minimum": 0.55,
        "confirmation_xetra_sessions": 2
      },
      "short_models": {
        "shock_10t": {
          "forecast_horizon_sessions": 10,
          "model_definition_id": 51,
          "trained_model_id": 41026,
          "artifact_sha256": "9e2c33b52dc077710ebfc4bd8dce7c4ae20ced9389b8683b13236ce390ba4c54"
        },
        "lower_tail_20t": {
          "forecast_horizon_sessions": 20,
          "model_definition_id": 50,
          "trained_model_id": 41025,
          "artifact_sha256": "9a4337814d91520cc197c4266bef4d4f2a58ce985f00b82940409dfb900ab6ac"
        },
        "bear_area_40t": {
          "forecast_horizon_sessions": 40,
          "model_definition_id": 49,
          "trained_model_id": 41024,
          "artifact_sha256": "bbcbe95342b44a04e62f6e9ca142f126c1d2677d9271da4b1edad14de8898f5d"
        }
      },
      "trend_indicator_artifact_sha256": "677c761a47b934e010b47ca5e86a3d72380e68f64543fdc9ad77149ca0483cd1"
    }
    $configuration$::jsonb,
    $evidence$
    {
      "backtest": {
        "cumulative_return": 0.2515313525447578,
        "max_drawdown": -0.22164168503124726,
        "closed_trades": 5,
        "comparison_baseline": "dynamic_tcn_observable",
        "comparison_return": -0.005362763466654985,
        "comparison_max_drawdown": -0.39987464177676024,
        "full_descriptive_return": 0.5900709497390835
      },
      "macmini_exact_rerun": {
        "canonical_stable_sha256": "a4b320b3b4eff40f77fa9a705eb0617eefea44391c6ddbca504d99c1103dbc5a",
        "manifest_sha256": "612d383a82c95bfebe1a5e4f3963ed000c72e60191e1bd701c7b168d32775ff9",
        "report_sha256": "a721058fb4f4e0b671c79983d238a6a5a33a0ea1e138ea78711bd19f9e510c77",
        "path_independent_outputs_compared": 29,
        "manifest_mismatches": 0
      },
      "active_runtime_bundle": {
        "source_path": "/Users/aktienki/pipeline-data/model-releases/pum-long-exit/pum-long-exit-s2-l-or-trend-p55-v1",
        "manifest_sha256": "59b6648d5a03fdafbd2e0233c584a49d723e48b16b617163b1373a233bf62f45",
        "policy_json_sha256": "b4f47bfef6ba2554fc18131909380e96be07eb1311e55ed7f46c34ba60a9b0ff"
      },
      "source_model_release_gates_passed": false,
      "policy_release_gate_passed": false,
      "activation_note": "Active recommendation by explicit user override; never an automatic execution instruction."
    }
    $evidence$::jsonb,
    now()
)
ON CONFLICT (policy_name) DO NOTHING;

-- A versioned policy must never be silently replaced under the same name.
DO $policy_contract$
DECLARE
    policy_row serving_exit_policies%ROWTYPE;
BEGIN
    SELECT *
      INTO STRICT policy_row
      FROM serving_exit_policies
     WHERE policy_name = 'pum_short_curve_trend_p55_v2';

    IF policy_row.policy_version <> 'pum-long-exit-s2-l-or-trend-p55-v1'
       OR policy_row.selection_procedure_version <> 'pum-short-indicator-long-exit-combination-search-v2'
       OR policy_row.core_policy_fingerprint <> 'c216205e3f7d35f9752f60acac9d02a717339f11c537d47dbf384e6ee2905f79'
       OR policy_row.instrument_id <> 1085
       OR policy_row.isin <> 'DE0006969603'
       OR policy_row.provider_symbol <> 'PUM:XETR'
       OR policy_row.status <> 'active_recommendation'
       OR NOT policy_row.is_active
       OR NOT policy_row.recommendation_only
       OR policy_row.automatic_execution
       OR policy_row.release_gate_passed
       OR NOT policy_row.user_override
       OR policy_row.activation_basis <> 'explicit_user_override'
       OR policy_row.comparison_baseline <> 'dynamic_tcn_observable'
       OR policy_row.signal_timing <> 'after_xetra_close'
       OR policy_row.execution_timing <> 'next_xetra_adjusted_open'
       OR policy_row.maximum_holding_sessions IS NOT NULL
       OR policy_row.formula <> '(S_2 & L) | I_trend_momentum_probability_p55'
       OR policy_row.configuration->'policy_contract'
            IS DISTINCT FROM serving_puma_exit_policy_contract()
       OR policy_row.evidence #>> '{active_runtime_bundle,source_path}'
            IS DISTINCT FROM '/Users/aktienki/pipeline-data/model-releases/pum-long-exit/pum-long-exit-s2-l-or-trend-p55-v1'
       OR policy_row.evidence #>> '{active_runtime_bundle,manifest_sha256}'
            IS DISTINCT FROM '59b6648d5a03fdafbd2e0233c584a49d723e48b16b617163b1373a233bf62f45'
       OR policy_row.evidence #>> '{active_runtime_bundle,policy_json_sha256}'
            IS DISTINCT FROM 'b4f47bfef6ba2554fc18131909380e96be07eb1311e55ed7f46c34ba60a9b0ff'
       OR policy_row.evidence->'source_model_release_gates_passed'
            IS DISTINCT FROM 'false'::jsonb
       OR policy_row.evidence->'policy_release_gate_passed'
            IS DISTINCT FROM 'false'::jsonb THEN
        RAISE EXCEPTION
            'Existing PUMA exit policy conflicts with the immutable v2 contract; use a new policy name';
    END IF;
END
$policy_contract$;

CREATE OR REPLACE VIEW serving_current_exit_recommendations AS
SELECT
    policy.policy_name,
    policy.policy_version,
    policy.selection_procedure_version,
    policy.core_policy_fingerprint,
    policy.instrument_id,
    instrument.symbol,
    policy.isin,
    policy.provider_symbol,
    policy.status AS policy_status,
    policy.is_active,
    policy.recommendation_only,
    policy.automatic_execution,
    policy.release_gate_passed,
    policy.user_override,
    policy.activation_basis,
    policy.comparison_baseline,
    policy.signal_timing,
    policy.execution_timing,
    policy.maximum_holding_sessions,
    policy.formula,
    policy.configuration,
    policy.evidence,
    observation.id AS observation_id,
    observation.input_schema_version,
    observation.market_session_date,
    observation.market_session_position,
    observation.signal_as_of,
    observation.valid_until,
    observation.observation_role,
    observation.state_only,
    observation.short_shock_10t,
    observation.short_shock_10t_score,
    observation.short_lower_tail_20t,
    observation.short_lower_tail_20t_score,
    observation.short_bear_area_40t,
    observation.short_bear_area_40t_score,
    observation.short_vote_count,
    observation.champion_10t,
    observation.champion_40t,
    observation.long_curve_raw,
    observation.long_curve_confirmed,
    observation.trend_momentum_probability,
    observation.indicator_raw,
    observation.indicator_confirmed,
    COALESCE(observation.exit_recommended, false) AS model_exit_recommended,
    COALESCE(observation.decision, 'NO_DATA') AS model_decision,
    CASE
        WHEN observation.id IS NULL THEN 'NO_DATA'
        WHEN observation.state_only THEN 'STATE_ONLY'
        WHEN CURRENT_TIMESTAMP >= observation.valid_until THEN 'EXPIRED'
        ELSE 'CURRENT'
    END AS recommendation_status,
    CASE
        WHEN observation.id IS NOT NULL
         AND observation.observation_role = 'current_recommendation'
         AND NOT observation.state_only
         AND observation.signal_as_of < observation.valid_until
         AND CURRENT_TIMESTAMP < observation.valid_until
            THEN observation.exit_recommended
        ELSE false
    END AS exit_recommended,
    CASE
        WHEN observation.id IS NOT NULL
         AND observation.observation_role = 'current_recommendation'
         AND NOT observation.state_only
         AND observation.signal_as_of < observation.valid_until
         AND CURRENT_TIMESTAMP < observation.valid_until
            THEN observation.decision
        ELSE 'NO_DATA'
    END AS decision,
    CASE
        WHEN observation.id IS NOT NULL
         AND observation.observation_role = 'current_recommendation'
         AND NOT observation.state_only
         AND observation.signal_as_of < observation.valid_until
         AND CURRENT_TIMESTAMP < observation.valid_until
            THEN observation.exit_recommended
        ELSE false
    END AS recommendation_actionable,
    observation.input_sha256,
    observation.source_lineage,
    observation.imported_at
FROM serving_exit_policies policy
JOIN serving_instruments instrument
  ON instrument.id = policy.instrument_id
LEFT JOIN LATERAL (
    SELECT signal.*
      FROM serving_exit_signal_observations signal
     WHERE signal.policy_name = policy.policy_name
     ORDER BY signal.market_session_position DESC, signal.imported_at DESC
     LIMIT 1
) observation ON true
WHERE policy.is_active;

COMMENT ON VIEW serving_current_exit_recommendations IS
    'Fail-closed current recommendation: state-only or expired observations read as NO_DATA; automatic_execution is always false.';

INSERT INTO serving_schema_versions (version, migration_name)
VALUES (28, '028_puma_exit_recommendation_policy.sql')
ON CONFLICT DO NOTHING;

DO $schema_contract$
DECLARE
    installed_name text;
BEGIN
    SELECT migration_name
      INTO installed_name
      FROM serving_schema_versions
     WHERE version = 28;

    IF installed_name IS DISTINCT FROM '028_puma_exit_recommendation_policy.sql' THEN
        RAISE EXCEPTION 'Serving schema version 28 is already assigned to %', installed_name;
    END IF;
END
$schema_contract$;

COMMIT;
