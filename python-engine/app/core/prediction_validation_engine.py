from __future__ import annotations

import logging

from app.repositories.prediction_validation_repository import (
    PredictionValidationRepository,
)
from app.repositories.validation_price_repository import (
    ValidationPriceRepository,
)

logger = logging.getLogger(__name__)


class PredictionValidationEngine:
    def __init__(self, session_factory):
        self.session_factory = session_factory

    def run(
        self,
        *,
        horizon_days: int = 5,
        limit: int | None = None,
    ) -> dict[str, int]:
        with self.session_factory() as session:
            predictions = PredictionValidationRepository(
                session
            ).due_predictions(
                horizon_days=horizon_days,
                limit=limit,
            )

        stats = {
            "total": len(predictions),
            "validated": 0,
            "skipped": 0,
            "failed": 0,
        }

        for prediction in predictions:
            try:
                validated = self.validate_one(
                    prediction=prediction,
                    horizon_days=horizon_days,
                )

                if validated:
                    stats["validated"] += 1
                else:
                    stats["skipped"] += 1

            except Exception:
                stats["failed"] += 1
                logger.exception(
                    "Prediction %s konnte nicht validiert werden.",
                    prediction["id"],
                )

        return stats

    def validate_one(
        self,
        *,
        prediction: dict,
        horizon_days: int,
    ) -> bool:
        with self.session_factory() as session:
            bars = ValidationPriceRepository(session).price_window(
                instrument_id=int(prediction["instrument_id"]),
                prediction_time=prediction["prediction_time"],
                horizon_days=horizon_days,
            )

        if len(bars) < horizon_days:
            return False

        current_price = float(prediction["current_price"])
        predicted_price = float(
            prediction["predicted_price_5d"]
        )

        target_bar = bars[-1]
        actual_price = float(target_bar["close"])
        future_high = max(float(bar["high"]) for bar in bars)
        future_low = min(float(bar["low"]) for bar in bars)

        actual_market_return = (
            actual_price - current_price
        ) / current_price

        actual_long_return = actual_market_return
        actual_short_return = -actual_market_return

        strategy = str(prediction["strategy"])
        actual_strategy_return = (
            actual_short_return
            if strategy == "short"
            else actual_long_return
        )

        prediction_error = actual_price - predicted_price
        prediction_error_pct = (
            prediction_error / predicted_price
            if predicted_price != 0
            else 0.0
        )

        predicted_market_return = float(
            prediction["market_return_5d"]
        )

        direction_correct = (
            predicted_market_return == 0
            and actual_market_return == 0
        ) or (
            predicted_market_return > 0
            and actual_market_return > 0
        ) or (
            predicted_market_return < 0
            and actual_market_return < 0
        )

        strategy_correct = actual_strategy_return > 0

        target_hit = (
            future_high >= predicted_price
            if strategy == "long"
            else future_low <= predicted_price
        )

        if strategy == "long":
            max_favorable_excursion = (
                future_high - current_price
            ) / current_price
            max_adverse_excursion = (
                future_low - current_price
            ) / current_price
        else:
            max_favorable_excursion = (
                current_price - future_low
            ) / current_price
            max_adverse_excursion = (
                current_price - future_high
            ) / current_price

        metadata = {
            "bars_used": horizon_days,
            "strategy": strategy,
            "prediction_time": str(
                prediction["prediction_time"]
            ),
            "target_bar_time": str(target_bar["bar_time"]),
        }

        with self.session_factory() as session:
            repository = PredictionValidationRepository(session)

            try:
                repository.save_validation(
                    prediction_id=int(prediction["id"]),
                    horizon_days=horizon_days,
                    target_time=target_bar["bar_time"],
                    actual_price=actual_price,
                    actual_market_return=actual_market_return,
                    actual_long_return=actual_long_return,
                    actual_short_return=actual_short_return,
                    actual_strategy_return=actual_strategy_return,
                    prediction_error=prediction_error,
                    prediction_error_pct=prediction_error_pct,
                    direction_correct=direction_correct,
                    strategy_correct=strategy_correct,
                    target_hit=target_hit,
                    future_high=future_high,
                    future_low=future_low,
                    max_favorable_excursion=max_favorable_excursion,
                    max_adverse_excursion=max_adverse_excursion,
                    metadata=metadata,
                )
                session.commit()
            except Exception:
                session.rollback()
                raise

        logger.info(
            "Prediction %s validiert: Strategie=%s, Rendite=%.4f",
            prediction["id"],
            strategy,
            actual_strategy_return,
        )

        return True
