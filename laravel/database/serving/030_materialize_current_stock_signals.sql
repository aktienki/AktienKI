BEGIN;

-- The canonical signal view is intentionally comprehensive and expensive to
-- plan. Web requests read this snapshot instead; prediction publication owns
-- refreshing it after a batch becomes complete.
SET LOCAL jit = off;

DROP TRIGGER IF EXISTS serving_refresh_current_stock_signals_after_insert
    ON serving_prediction_batches;
DROP TRIGGER IF EXISTS serving_refresh_current_stock_signals_after_update
    ON serving_prediction_batches;
DROP TRIGGER IF EXISTS serving_refresh_current_stock_signals_after_active_model_change
    ON serving_active_models;
DROP TRIGGER IF EXISTS serving_refresh_current_stock_signals_after_filter_change
    ON serving_indicator_entry_filters;
DROP FUNCTION IF EXISTS refresh_serving_current_stock_signals_materialized();
DROP MATERIALIZED VIEW IF EXISTS serving_current_stock_signals_materialized;

CREATE MATERIALIZED VIEW serving_current_stock_signals_materialized AS
SELECT *
FROM serving_current_stock_signals;

CREATE UNIQUE INDEX serving_current_stock_signals_materialized_instrument_key
    ON serving_current_stock_signals_materialized (instrument_id);
CREATE INDEX serving_current_stock_signals_materialized_batch_idx
    ON serving_current_stock_signals_materialized (batch_id);

COMMENT ON MATERIALIZED VIEW serving_current_stock_signals_materialized IS
    'Read-optimized snapshot of serving_current_stock_signals; refreshed atomically after completed prediction batches and signal-policy changes.';

CREATE FUNCTION refresh_serving_current_stock_signals_materialized()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $function$
BEGIN
    -- This view has a very large generated plan. LLVM JIT compilation costs
    -- substantially more than executing it for the compact serving dataset.
    PERFORM set_config('jit', 'off', true);
    REFRESH MATERIALIZED VIEW serving_current_stock_signals_materialized;
    RETURN NULL;
END
$function$;

CREATE TRIGGER serving_refresh_current_stock_signals_after_insert
AFTER INSERT ON serving_prediction_batches
FOR EACH ROW
WHEN (NEW.status = 'complete')
EXECUTE FUNCTION refresh_serving_current_stock_signals_materialized();

CREATE TRIGGER serving_refresh_current_stock_signals_after_update
AFTER UPDATE OF status ON serving_prediction_batches
FOR EACH ROW
WHEN (NEW.status = 'complete' AND OLD.status IS DISTINCT FROM NEW.status)
EXECUTE FUNCTION refresh_serving_current_stock_signals_materialized();

CREATE TRIGGER serving_refresh_current_stock_signals_after_active_model_change
AFTER INSERT OR UPDATE OR DELETE ON serving_active_models
FOR EACH STATEMENT
EXECUTE FUNCTION refresh_serving_current_stock_signals_materialized();

CREATE TRIGGER serving_refresh_current_stock_signals_after_filter_change
AFTER INSERT OR UPDATE OR DELETE ON serving_indicator_entry_filters
FOR EACH STATEMENT
EXECUTE FUNCTION refresh_serving_current_stock_signals_materialized();

INSERT INTO serving_schema_versions (version, migration_name)
VALUES (30, '030_materialize_current_stock_signals.sql')
ON CONFLICT DO NOTHING;

DO $schema_contract$
DECLARE
    installed_name text;
BEGIN
    SELECT migration_name
      INTO installed_name
      FROM serving_schema_versions
     WHERE version = 30;

    IF installed_name IS DISTINCT FROM '030_materialize_current_stock_signals.sql' THEN
        RAISE EXCEPTION 'Serving schema version 30 is already assigned to %', installed_name;
    END IF;
END
$schema_contract$;

COMMIT;
