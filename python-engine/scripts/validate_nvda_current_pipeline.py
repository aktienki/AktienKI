from __future__ import annotations

import sys

sys.path.insert(0, "/Users/silviotaubert/Downloads/python-engine")
sys.path.insert(0, "/Users/silviotaubert/Downloads/python-engine/scripts")

from app.database import Database
import macbook_international_rotating_pipeline as pipeline


def main() -> int:
    database = Database()
    try:
        row = database.fetch_one(
            """
            SELECT i.id, i.symbol,
              COALESCE((
                SELECT market_index.symbol
                FROM index_memberships membership
                JOIN market_indices market_index ON market_index.id=membership.market_index_id
                WHERE membership.instrument_id=i.id
                  AND membership.removed_at IS NULL
                ORDER BY membership.weight DESC NULLS LAST, market_index.id
                LIMIT 1
              ), '^GSPC') AS home_index_symbol,
              ARRAY(
                SELECT horizon
                FROM unnest(ARRAY[5,10,15,20]) horizon
                WHERE NOT EXISTS (
                  SELECT 1 FROM trained_models model
                  WHERE model.instrument_id=i.id
                    AND model.deleted_at IS NULL
                    AND model.status='active'
                    AND model.feature_set_version='triple_daily_macro_v1'
                    AND model.prediction_horizon_minutes=horizon*1440
                )
              ) AS missing_models,
              ARRAY(
                SELECT horizon
                FROM unnest(ARRAY[5,10,15,20]) horizon
                WHERE NOT EXISTS (
                  SELECT 1
                  FROM walk_forward_backtest_scores score
                  JOIN walk_forward_backtest_runs run ON run.id=score.run_id
                  WHERE score.instrument_id=i.id
                    AND score.horizon_days=horizon
                    AND run.status='completed'
                    AND score.trade_count>0
                )
              ) AS missing_walk_forward,
              NOT EXISTS (
                SELECT 1 FROM market_context_predictions context
                WHERE context.scope_type='stock_phase20'
                  AND context.scope_key=i.id::text
                  AND context.meta->>'source'='pytorch_stock_three_phase_gru_20t'
              ) AS missing_phase_filter
            FROM instruments i
            WHERE i.symbol='NVDA'
            LIMIT 1
            """
        )
    finally:
        database.close()

    if row is None:
        raise RuntimeError("NVDA was not found")

    stock = dict(row)
    print(f"NVDA_VALIDATION_INPUT {stock}", flush=True)
    pipeline.prepare_index_contexts()
    successful = pipeline.process(stock)
    print(f"NVDA_VALIDATION_RESULT successful={successful}", flush=True)
    return 0 if successful else 2


if __name__ == "__main__":
    raise SystemExit(main())
