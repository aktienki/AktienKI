from __future__ import annotations

import math
from pathlib import Path

import joblib
import pandas as pd

from app.models.decision import Decision
from app.repositories.latest_feature_repository import LatestFeatureRepository
from app.repositories.prediction_repository import PredictionRepository
from app.repositories.trained_model_loader_repository import (
    TrainedModelLoaderRepository,
)
from app.scoring.decision_scorer import DecisionScorer


class PredictionEngine:
    def __init__(self, session_factory):
        self.session_factory = session_factory
        self.scorer = DecisionScorer()

    def predict(
        self,
        *,
        instrument_id: int,
        algorithm: str = "xgboost",
        target_name: str = "target_return_5d",
        interval: str = "1d",
    ) -> dict:
        with self.session_factory() as session:
            model_info = TrainedModelLoaderRepository(
                session
            ).latest_ready(
                instrument_id=instrument_id,
                algorithm=algorithm,
                target_name=target_name,
            )

            if model_info is None:
                raise RuntimeError(
                    "Kein trainiertes Modell gefunden."
                )

            feature_row = LatestFeatureRepository(
                session
            ).latest(
                instrument_id=instrument_id,
                interval=interval,
                feature_version=model_info["feature_version"],
            )

            if feature_row is None:
                raise RuntimeError(
                    "Keine Feature-Store-Zeile gefunden."
                )

        artifact_path = Path(model_info["artifact_path"])

        if not artifact_path.exists():
            raise RuntimeError(
                f"Modellartefakt fehlt: {artifact_path}"
            )

        model = joblib.load(artifact_path)

        feature_names = list(model_info["feature_names"])
        feature_values = {
            name: float(feature_row[name])
            for name in feature_names
        }

        frame = pd.DataFrame([feature_values])
        predicted_return = float(model.predict(frame)[0])

        current_price = float(feature_row["close"])
        predicted_price = current_price * (1.0 + predicted_return)

        price_difference = predicted_price - current_price
        market_return = price_difference / current_price
        long_return = market_return
        short_return = -market_return

        strategy = "short" if market_return < 0 else "long"
        strategy_return = (
            short_return if strategy == "short" else long_return
        )

        confidence = self._confidence_from_metrics(
            model_info.get("metrics") or {}
        )

        trend_strength = self._trend_strength(
            close=current_price,
            ema_20=float(feature_row["ema_20"]),
            ema_50=float(feature_row["ema_50"]),
            ema_200=float(feature_row["ema_200"]),
            strategy=strategy,
        )

        scores = self.scorer.score(
            market_return=market_return,
            confidence=confidence,
            volatility=(
                None
                if feature_row["volatility_20"] is None
                else float(feature_row["volatility_20"])
            ),
            trend_strength=trend_strength,
        )

        explanation = self._explanation(
            strategy=strategy,
            market_return=market_return,
            rsi=(
                None
                if feature_row["rsi_14"] is None
                else float(feature_row["rsi_14"])
            ),
            trend_strength=trend_strength,
            confidence=confidence,
        )

        decision = Decision(
            instrument_id=instrument_id,
            trained_model_id=int(model_info["id"]),
            interval=interval,
            current_price=current_price,
            predicted_price_5d=predicted_price,
            price_difference_5d=price_difference,
            market_return_5d=market_return,
            long_return_5d=long_return,
            short_return_5d=short_return,
            strategy=strategy,
            strategy_return_5d=strategy_return,
            direction_score=float(scores["direction_score"]),
            signal_strength=float(scores["signal_strength"]),
            confidence=float(scores["confidence"]),
            risk_score=float(scores["risk_score"]),
            trend_strength=float(scores["trend_strength"]),
            ai_score=float(scores["ai_score"]),
            signal=str(scores["signal"]),
            explanation=explanation,
            metadata={
                "algorithm": algorithm,
                "target_name": target_name,
                "feature_version": model_info["feature_version"],
                "feature_bar_time": str(feature_row["bar_time"]),
                "predicted_return_raw": predicted_return,
            },
        )

        with self.session_factory() as session:
            repository = PredictionRepository(session)

            try:
                prediction_id = repository.save(decision)
                session.commit()
            except Exception:
                session.rollback()
                raise

        return {
            "prediction_id": prediction_id,
            **decision.to_dict(),
        }

    @staticmethod
    def _confidence_from_metrics(metrics: dict) -> float:
        test = metrics.get("test", {})
        direction_accuracy = float(
            test.get("direction_accuracy", 0.5)
        )
        r2 = float(test.get("r2", 0.0))

        confidence = (
            direction_accuracy * 70.0
            + max(0.0, min(1.0, r2)) * 30.0
        )

        return max(0.0, min(100.0, confidence))

    @staticmethod
    def _trend_strength(
        *,
        close: float,
        ema_20: float,
        ema_50: float,
        ema_200: float,
        strategy: str,
    ) -> float:
        if close <= 0:
            return 0.0

        if strategy == "long":
            confirmations = [
                close > ema_20,
                ema_20 > ema_50,
                ema_50 > ema_200,
            ]
        else:
            confirmations = [
                close < ema_20,
                ema_20 < ema_50,
                ema_50 < ema_200,
            ]

        return (sum(confirmations) / len(confirmations)) * 100.0

    @staticmethod
    def _explanation(
        *,
        strategy: str,
        market_return: float,
        rsi: float | None,
        trend_strength: float,
        confidence: float,
    ) -> dict:
        reasons = [
            {
                "type": "expected_market_move",
                "value": market_return,
                "text": (
                    "Erwartete steigende Marktbewegung."
                    if market_return >= 0
                    else "Erwartete fallende Marktbewegung."
                ),
            },
            {
                "type": "trend_confirmation",
                "value": trend_strength,
                "text": f"Trendbestätigung: {trend_strength:.1f} %.",
            },
            {
                "type": "model_confidence",
                "value": confidence,
                "text": f"Modellkonfidenz: {confidence:.1f} %.",
            },
        ]

        if rsi is not None:
            reasons.append(
                {
                    "type": "rsi",
                    "value": rsi,
                    "text": f"RSI 14 liegt bei {rsi:.1f}.",
                }
            )

        return {
            "strategy": strategy,
            "summary": (
                "Long-Chance auf Basis des erwarteten Zielkurses."
                if strategy == "long"
                else "Short-Chance auf Basis des erwarteten Zielkurses."
            ),
            "reasons": reasons,
        }
