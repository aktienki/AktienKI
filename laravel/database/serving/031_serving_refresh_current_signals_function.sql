BEGIN;

-- The materialized view is owned by `postgres`; only its owner may run
-- REFRESH MATERIALIZED VIEW. Expose a SECURITY DEFINER wrapper so the lean
-- application role can trigger a refresh as a scheduled safety net without
-- gaining broader privileges. The trigger-driven refresh on completed
-- prediction batches remains the primary path.

SET LOCAL jit = off;

CREATE OR REPLACE FUNCTION serving_refresh_current_signals(concurrent boolean DEFAULT true)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $function$
BEGIN
    PERFORM set_config('jit', 'off', true);
    IF concurrent THEN
        REFRESH MATERIALIZED VIEW CONCURRENTLY serving_current_stock_signals_materialized;
    ELSE
        REFRESH MATERIALIZED VIEW serving_current_stock_signals_materialized;
    END IF;
END
$function$;

REVOKE ALL ON FUNCTION serving_refresh_current_signals(boolean) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION serving_refresh_current_signals(boolean) TO aktienki_app;

INSERT INTO serving_schema_versions (version, migration_name)
VALUES (31, '031_serving_refresh_current_signals_function.sql')
ON CONFLICT DO NOTHING;

DO $schema_contract$
DECLARE
    installed_name text;
BEGIN
    SELECT migration_name
      INTO installed_name
      FROM serving_schema_versions
     WHERE version = 31;

    IF installed_name IS DISTINCT FROM '031_serving_refresh_current_signals_function.sql' THEN
        RAISE EXCEPTION 'Serving schema version 31 is already assigned to %', installed_name;
    END IF;
END
$schema_contract$;

COMMIT;
