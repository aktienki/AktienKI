BEGIN;

-- The fixed-horizon release contains 10T, 20T and 40T configurations.
ALTER TABLE serving_predictions
    DROP CONSTRAINT IF EXISTS serving_predictions_horizon_check;
ALTER TABLE serving_predictions
    ADD CONSTRAINT serving_predictions_horizon_check
    CHECK (horizon IN (5, 10, 15, 20, 40));

-- Virtual status rows keep the database lean: no separate status table and no
-- duplicated metric payload are needed. Every release update changes the view
-- immediately because the source of truth remains the compact release JSON.
CREATE OR REPLACE VIEW serving_model_horizon_status AS
SELECT
    i.id AS instrument_id,
    i.symbol,
    r.id AS release_id,
    horizon.key::smallint AS horizon,
    variant.key AS variant,
    (r.compact_metrics->'active_models'->horizon.key->>'variant') = variant.key
        AS selected_for_prediction,
    COALESCE(variant.value->'prediction_status'->>'status', 'ineligible_performance')
        AS prediction_status,
    COALESCE(
        (variant.value->'prediction_status'->>'prediction_enabled')::boolean,
        false
    ) AS prediction_enabled,
    COALESCE(variant.value->'prediction_status'->'failed_gates', '[]'::jsonb)
        AS failed_gates,
    COALESCE(variant.value->'entry_policy', '{}'::jsonb) AS entry_policy,
    COALESCE(variant.value->'metrics', '{}'::jsonb) AS performance,
    r.artifact_manifest,
    r.dataset_cutoff,
    a.activated_at
FROM serving_active_models AS a
JOIN serving_releases AS r ON r.id = a.release_id
JOIN serving_instruments AS i ON i.id = a.instrument_id
CROSS JOIN LATERAL jsonb_each(r.compact_metrics->'horizons') AS horizon(key, value)
CROSS JOIN LATERAL jsonb_each(
    jsonb_build_object(
        'standard', horizon.value->'standard',
        'pure_tcn', horizon.value->'pure_tcn'
    )
) AS variant(key, value);

-- This is the only scope list a serving worker should consume. Ineligible or
-- non-selected configurations never reach model loading or prediction writes.
CREATE OR REPLACE VIEW serving_prediction_scopes AS
SELECT *
FROM serving_model_horizon_status
WHERE selected_for_prediction
  AND prediction_enabled;

COMMENT ON VIEW serving_prediction_scopes IS
    'Only selected, OOS-eligible model/horizon configurations; rejected scopes must not create prediction rows.';

COMMIT;
