from __future__ import annotations

import json
from sqlalchemy import text


class PredictionValidationRepository:
    def __init__(self, session):
        self.session = session

    def due_predictions(
        self,
        *,
        horizon_days: int = 5,
        limit: int | None = None,
    ) -> list[dict]:
        limit_sql = ""
        parameters: dict = {
            "horizon_days": horizon_days,
        }

        if limit is not None:
            limit_sql = "LIMIT :limit"
            parameters["limit"] = limit

        rows = self.session.execute(
            text(
                f"""
                SELECT
                    p.id,
                    p.instrument_id,
                    p.prediction_time,
                    p.current_price,
                    p.predicted_price_5d,
                    p.strategy,
                    p.market_return_5d,
                    p.strategy_return_5d
                FROM predictions p
                LEFT JOIN prediction_validations pv
                    ON pv.prediction_id = p.id
                   AND pv.validation_horizon_days = :horizon_days
                WHERE p.status = 'pending_validation'
                  AND pv.id IS NULL
                  AND p.prediction_time <= NOW() - (:horizon_days || ' days')::interval
                ORDER BY p.prediction_time
                {limit_sql}
                """
            ),
            parameters,
        ).mappings().all()

        return [dict(row) for row in rows]

    def save_validation(
        self,
        *,
        prediction_id: int,
        horizon_days: int,
        target_time,
        actual_price: float,
        actual_market_return: float,
        actual_long_return: float,
        actual_short_return: float,
        actual_strategy_return: float,
        prediction_error: float,
        prediction_error_pct: float,
        direction_correct: bool,
        strategy_correct: bool,
        target_hit: bool,
        future_high: float | None,
        future_low: float | None,
        max_favorable_excursion: float | None,
        max_adverse_excursion: float | None,
        metadata: dict,
    ) -> int:
        validation_id = int(
            self.session.execute(
                text(
                    """
                    INSERT INTO prediction_validations (
                        prediction_id,
                        validation_horizon_days,
                        target_time,
                        actual_price,
                        actual_market_return,
                        actual_long_return,
                        actual_short_return,
                        actual_strategy_return,
                        prediction_error,
                        prediction_error_pct,
                        direction_correct,
                        strategy_correct,
                        target_hit,
                        future_high,
                        future_low,
                        max_favorable_excursion,
                        max_adverse_excursion,
                        validated_at,
                        metadata,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :prediction_id,
                        :horizon_days,
                        :target_time,
                        :actual_price,
                        :actual_market_return,
                        :actual_long_return,
                        :actual_short_return,
                        :actual_strategy_return,
                        :prediction_error,
                        :prediction_error_pct,
                        :direction_correct,
                        :strategy_correct,
                        :target_hit,
                        :future_high,
                        :future_low,
                        :max_favorable_excursion,
                        :max_adverse_excursion,
                        NOW(),
                        CAST(:metadata AS jsonb),
                        NOW(),
                        NOW()
                    )
                    ON CONFLICT (
                        prediction_id,
                        validation_horizon_days
                    )
                    DO UPDATE SET
                        target_time = EXCLUDED.target_time,
                        actual_price = EXCLUDED.actual_price,
                        actual_market_return = EXCLUDED.actual_market_return,
                        actual_long_return = EXCLUDED.actual_long_return,
                        actual_short_return = EXCLUDED.actual_short_return,
                        actual_strategy_return = EXCLUDED.actual_strategy_return,
                        prediction_error = EXCLUDED.prediction_error,
                        prediction_error_pct = EXCLUDED.prediction_error_pct,
                        direction_correct = EXCLUDED.direction_correct,
                        strategy_correct = EXCLUDED.strategy_correct,
                        target_hit = EXCLUDED.target_hit,
                        future_high = EXCLUDED.future_high,
                        future_low = EXCLUDED.future_low,
                        max_favorable_excursion = EXCLUDED.max_favorable_excursion,
                        max_adverse_excursion = EXCLUDED.max_adverse_excursion,
                        validated_at = NOW(),
                        metadata = EXCLUDED.metadata,
                        updated_at = NOW()
                    RETURNING id
                    """
                ),
                {
                    "prediction_id": prediction_id,
                    "horizon_days": horizon_days,
                    "target_time": target_time,
                    "actual_price": actual_price,
                    "actual_market_return": actual_market_return,
                    "actual_long_return": actual_long_return,
                    "actual_short_return": actual_short_return,
                    "actual_strategy_return": actual_strategy_return,
                    "prediction_error": prediction_error,
                    "prediction_error_pct": prediction_error_pct,
                    "direction_correct": direction_correct,
                    "strategy_correct": strategy_correct,
                    "target_hit": target_hit,
                    "future_high": future_high,
                    "future_low": future_low,
                    "max_favorable_excursion": max_favorable_excursion,
                    "max_adverse_excursion": max_adverse_excursion,
                    "metadata": json.dumps(metadata),
                },
            ).scalar_one()
        )

        self.session.execute(
            text(
                """
                UPDATE predictions
                SET status = 'validated',
                    updated_at = NOW()
                WHERE id = :prediction_id
                """
            ),
            {"prediction_id": prediction_id},
        )

        return validation_id
