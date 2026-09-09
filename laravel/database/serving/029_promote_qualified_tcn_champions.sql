BEGIN;

-- The release JSON remains immutable. Champion selection is derived from its
-- OOS evidence, so every newly activated release receives the same policy.
CREATE OR REPLACE VIEW serving_model_horizon_status AS
WITH model_variants AS (
    SELECT
        i.id AS instrument_id,
        i.symbol,
        r.id AS release_id,
        horizon.key::smallint AS horizon,
        variant.key AS variant,
        variant.value AS variant_payload,
        horizon.value AS horizon_payload,
        r.artifact_manifest,
        r.dataset_cutoff,
        a.activated_at
    FROM serving_active_models AS a
    JOIN serving_releases AS r ON r.id = a.release_id
    JOIN serving_instruments AS i ON i.id = a.instrument_id
    CROSS JOIN LATERAL jsonb_each(r.compact_metrics->'horizons') AS horizon(key, value)
    CROSS JOIN LATERAL jsonb_each(jsonb_build_object(
        'standard', horizon.value->'standard',
        'pure_tcn', horizon.value->'pure_tcn'
    )) AS variant(key, value)
), selected AS (
    SELECT *,
        CASE
            WHEN COALESCE((horizon_payload->'pure_tcn'->'prediction_status'->>'prediction_enabled')::boolean, false)
             AND COALESCE((horizon_payload->'pure_tcn'->'prediction_status'->'quality_gate'->>'passed')::boolean, false)
             AND (
                NOT COALESCE((horizon_payload->'standard'->'prediction_status'->'quality_gate'->>'passed')::boolean, false)
                OR (
                    (horizon_payload->'pure_tcn'->'metrics'->>'average_net_trade')::double precision
                        > (horizon_payload->'standard'->'metrics'->>'average_net_trade')::double precision
                    AND (horizon_payload->'pure_tcn'->'metrics'->>'profit_factor')::double precision
                        > (horizon_payload->'standard'->'metrics'->>'profit_factor')::double precision
                )
             )
            THEN 'pure_tcn'
            ELSE 'standard'
        END AS selected_variant
    FROM model_variants
)
SELECT
    instrument_id,
    symbol,
    release_id,
    horizon,
    variant,
    selected_variant = variant AS selected_for_prediction,
    COALESCE(variant_payload->'prediction_status'->>'status', 'not_evaluated') AS prediction_status,
    COALESCE((variant_payload->'prediction_status'->>'prediction_enabled')::boolean, false) AS prediction_enabled,
    COALESCE(variant_payload->'prediction_status'->'failed_gates', '[]'::jsonb) AS failed_gates,
    COALESCE(variant_payload->'entry_policy', '{}'::jsonb) AS entry_policy,
    COALESCE(variant_payload->'metrics', '{}'::jsonb) AS performance,
    CASE WHEN variant = 'pure_tcn' THEN (
        SELECT jsonb_object_agg(
            artifact.key,
            CASE
                WHEN artifact.value->>'kind' = 'tcn_confirmation_model'
                 AND (artifact.value->>'horizon')::smallint = horizon
                THEN jsonb_set(
                    jsonb_set(artifact.value, '{kind}', '"model"'::jsonb),
                    '{variant}', '"pure_tcn"'::jsonb
                )
                ELSE artifact.value
            END
        )
        FROM jsonb_each(artifact_manifest) AS artifact(key, value)
    ) ELSE artifact_manifest END AS artifact_manifest,
    dataset_cutoff,
    activated_at,
    COALESCE(variant_payload->'prediction_status'->'model_quality'->>'quality_class', 'not_evaluated') AS model_quality_class,
    COALESCE(variant_payload->'prediction_status'->'model_quality'->>'display_name', 'Nicht bewertet') AS model_quality_label,
    COALESCE((variant_payload->'prediction_status'->'quality_gate'->>'passed')::boolean, false) AS quality_gate_passed
FROM selected;

CREATE OR REPLACE VIEW serving_prediction_scopes AS
SELECT *
FROM serving_model_horizon_status
WHERE selected_for_prediction
  AND prediction_enabled;

COMMENT ON VIEW serving_prediction_scopes IS
    'Best qualified Standard/TCN champion per active release and horizon; rejected scopes never reach inference.';

COMMIT;
