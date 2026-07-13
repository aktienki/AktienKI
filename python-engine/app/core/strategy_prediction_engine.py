from __future__ import annotations

from pathlib import Path

import joblib
import pandas as pd

from app.models.decision import Decision
from app.repositories.cross_asset_repository import CrossAssetRepository
from app.repositories.prediction_repository import PredictionRepository
from app.repositories.raw_price_repository import RawPriceRepository
from app.repositories.strategy_model_loader_repository import (
    StrategyModelLoaderRepository,
)
from app.repositories.strategy_profile_repository import (
    StrategyProfileRepository,
)
from app.scoring.decision_scorer import DecisionScorer
from app.strategies.dynamic_feature_builder import DynamicFeatureBuilder


class StrategyPredictionEngine:
    def __init__(self, session_factory):
        self.session_factory = session_factory
        self.builder = DynamicFeatureBuilder()
        self.scorer = DecisionScorer()

    def predict(
        self,
        *,
        strategy_code: str,
        instrument_id: int,
        algorithm: str = "xgboost",
    ) -> dict:
        with self.session_factory() as session:
            strategy = StrategyProfileRepository(session).get_by_code(
                strategy_code
            )

            if strategy is None:
                raise RuntimeError(
                    f"Strategy Profile '{strategy_code}' wurde nicht gefunden."
                )

            model_info = StrategyModelLoaderRepository(
                session
            ).latest_ready(
                strategy_code=strategy_code,
                instrument_id=instrument_id,
                algorithm=algorithm,
            )

            if model_info is None:
                raise RuntimeError(
                    "Kein fertiges Strategy-Modell gefunden."
                )

            base_frame = RawPriceRepository(session).load(
                instrument_id=instrument_id,
                interval=strategy.interval,
            )

            cross_asset_frames = CrossAssetRepository(
                session
            ).load_frames(
                strategy_profile_id=strategy.id,
                interval=strategy.interval,
            )

        if base_frame.empty:
            raise RuntimeError(
                "Keine Kursdaten für das Zielinstrument gefunden."
            )

        dynamic_frame = self.builder.build(
            base_frame=base_frame,
            strategy=strategy,
            cross_asset_frames=cross_asset_frames,
        )

        feature_names = list(model_info["feature_names"])
        latest = dynamic_frame.iloc[-1]

        missing = [
            name
            for name in feature_names
            if name not in dynamic_frame.columns
            or pd.isna(latest.get(name))
        ]

        if missing:
            raise RuntimeError(
                "Die neuesten Strategy-Features sind unvollständig: "
                + ", ".join(missing[:20])
            )

        artifact_path = Path(model_info["artifact_path"])
        if not artifact_path.exists():
            raise RuntimeError(
                f"Modellartefakt fehlt: {artifact_path}"
            )

        model = joblib.load(artifact_path)

        feature_frame = pd.DataFrame(
            [{
                name: float(latest[name])
                for name in feature_names
            }]
        )

        predicted_return = float(
            model.predict(feature_frame)[0]
        )

        horizon = int(strategy.target_horizon_days)
        current_price = float(latest["close"])
        predicted_price = current_price * (1.0 + predicted_return)

        price_difference = predicted_price - current_price
        market_return = price_difference / current_price
        long_return = market_return
        short_return = -market_return

        strategy_direction = (
            "short" if market_return < 0 else "long"
        )

        strategy_return = (
            short_return
            if strategy_direction == "short"
            else long_return
        )

        metrics = model_info.get("metrics") or {}
        confidence = self._confidence(metrics)

        trend_strength = 50.0
        if all(
            name in latest
            for name in ("ema_20", "ema_50", "ema_200")
        ):
            ema_20 = float(latest["ema_20"])
            ema_50 = float(latest["ema_50"])
            ema_200 = float(latest["ema_200"])

            if strategy_direction == "long":
                confirmations = [
                    current_price > ema_20,
                    ema_20 > ema_50,
                    ema_50 > ema_200,
                ]
            else:
                confirmations = [
                    current_price < ema_20,
                    ema_20 < ema_50,
                    ema_50 < ema_200,
                ]

            trend_strength = sum(confirmations) / 3 * 100

        volatility = None
        for name in ("volatility_20", "atr_14"):
            if name in latest and not pd.isna(latest[name]):
                volatility = float(latest[name])
                break

        scores = self.scorer.score(
            market_return=market_return,
            confidence=confidence,
            volatility=volatility,
            trend_strength=trend_strength,
        )

        explanation = {
            "strategy_profile": strategy.code,
            "strategy_version": strategy.version,
            "direction": strategy_direction,
            "target_horizon_days": horizon,
            "cross_assets": sorted(
                cross_asset_frames.keys()
            ),
            "summary": (
                f"Long-Chance für {horizon} Handelstage."
                if strategy_direction == "long"
                else f"Short-Chance für {horizon} Handelstage."
            ),
        }

        decision = Decision(
            instrument_id=instrument_id,
            trained_model_id=int(model_info["id"]),
            interval=strategy.interval,
            current_price=current_price,
            predicted_price_5d=predicted_price,
            price_difference_5d=price_difference,
            market_return_5d=market_return,
            long_return_5d=long_return,
            short_return_5d=short_return,
            strategy=strategy_direction,
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
                "strategy_profile_id": strategy.id,
                "strategy_code": strategy.code,
                "strategy_version": strategy.version,
                "algorithm": algorithm,
                "target_name": model_info["target_name"],
                "target_horizon_days": horizon,
                "cross_asset_aliases": sorted(
                    cross_asset_frames.keys()
                ),
                "feature_names": feature_names,
                "feature_bar_time": str(
                    dynamic_frame.index[-1]
                ),
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
            "strategy_code": strategy.code,
            "strategy_version": strategy.version,
            "target_horizon_days": horizon,
            **decision.to_dict(),
        }

    @staticmethod
    def _confidence(metrics: dict) -> float:
        test = metrics.get("test", {})
        direction_accuracy = float(
            test.get("direction_accuracy", 0.5)
        )
        r2 = float(test.get("r2", 0.0))

        value = (
            direction_accuracy * 70.0
            + max(0.0, min(1.0, r2)) * 30.0
        )

        return max(0.0, min(100.0, value))
