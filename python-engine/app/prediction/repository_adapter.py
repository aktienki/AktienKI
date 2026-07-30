from __future__ import annotations

from decimal import Decimal

from app.models.prediction import Prediction
from app.prediction.dto import PredictionOutput
from app.repositories.prediction_repository import PredictionRepository


class PredictionRepositoryAdapter:
    def __init__(
        self,
        repository: PredictionRepository,
        *,
        trained_model_id: int | None = None,
        prediction_run_id: int | None = None,
        timeframe: str = "1d",
        horizon: int = 5,
        model_scope: str = "long_term",
    ) -> None:
        if trained_model_id is None and prediction_run_id is None:
            raise ValueError("trained_model_id oder prediction_run_id ist erforderlich")
        self.repository = repository
        self.trained_model_id = trained_model_id
        self.prediction_run_id = prediction_run_id
        self.timeframe = timeframe
        self.horizon = horizon
        self.model_scope = model_scope

    @staticmethod
    def _d(value: float | None) -> Decimal | None:
        return None if value is None else Decimal(str(value))

    def insert_prediction(self, output: PredictionOutput) -> Prediction:
        market = output.predicted_return
        long_ret = max(market, 0.0)
        short_ret = max(-market, 0.0)
        strategy = "long" if market > 0 else "short" if market < 0 else "hold"
        direction = "up" if market > 0 else "down" if market < 0 else "neutral"
        factor_ratings = output.metadata.get("factor_ratings") or {}
        drawdown_risk = factor_ratings.get("drawdown_risk") or {}
        risk_score = drawdown_risk.get("value")
        model_results = {
            "model_version_id": output.model_version_id,
            "model_name": output.model_name,
            "target_name": output.target_name,
            "raw_prediction": output.raw_prediction,
            "feature_names": list(output.feature_names),
            "market_move": output.market_move,
        }
        kwargs = {
            "instrument_id": output.instrument_id,
            "trained_model_id": self.trained_model_id,
            "prediction_run_id": self.prediction_run_id,
            "prediction_time": output.prediction_timestamp,
            "prediction_date": output.prediction_timestamp,
            "target_date": output.target_timestamp,
            "interval": self.timeframe,
            "timeframe": self.timeframe,
            "current_price": self._d(output.current_price),
            "strategy": strategy,
            "signal": output.signal,
            "predicted_price": self._d(output.predicted_price),
            "predicted_market_return": self._d(market),
            "predicted_strategy_return": self._d(output.strategy_return),
            "prediction_score": self._d(output.prediction_score),
            "direction_score": self._d(output.prediction_score),
            "signal_strength": self._d(output.prediction_score),
            "confidence": self._d(output.confidence),
            "risk_score": self._d(risk_score),
            "ai_score": self._d(output.prediction_score),
            "confidence_score": self._d(output.confidence),
            "direction": direction,
            "model_scope": self.model_scope,
            "prediction_horizon_minutes": self.horizon * (1440 if self.timeframe == "1d" else 60 if self.timeframe == "1h" else 15),
            "risk_level": output.risk_level,
            "stop_loss_percent": self._d(output.stop_loss_percent),
            "take_profit_percent": self._d(output.take_profit_percent),
            "risk_reward": self._d(output.risk_reward),
            "model_results": model_results,
            "explanation": {
                "risk_level": output.risk_level,
                "stop_loss_percent": output.stop_loss_percent,
                "take_profit_percent": output.take_profit_percent,
                "risk_reward": output.risk_reward,
            },
            "metadata": {**output.metadata, **model_results},
        }
        suffix = {5: "5d", 10: "10d", 20: "20d"}.get(self.horizon, "5d")
        kwargs[f"predicted_price_{suffix}"] = self._d(output.predicted_price)
        kwargs[f"price_difference_{suffix}"] = self._d(output.market_move)
        kwargs[f"market_return_{suffix}"] = self._d(market)
        kwargs[f"long_return_{suffix}"] = self._d(long_ret)
        kwargs[f"short_return_{suffix}"] = self._d(short_ret)
        kwargs[f"strategy_return_{suffix}"] = self._d(output.strategy_return)
        prediction = Prediction(**kwargs)
        return self.repository.upsert(prediction)
