from datetime import datetime, timezone
from decimal import Decimal

from app.prediction.dto import PredictionOutput
from app.prediction.repository_adapter import PredictionRepositoryAdapter


class MemoryRepository:
    def __init__(self):
        self.value = None

    def upsert(self, prediction):
        self.value = prediction
        prediction.id = 77
        return prediction


def test_adapter_builds_real_prediction_dataclass():
    repository = MemoryRepository()
    adapter = PredictionRepositoryAdapter(
        repository,
        prediction_run_id=12,
        timeframe="1d",
    )
    now = datetime.now(timezone.utc)
    output = PredictionOutput(
        instrument_id=5,
        prediction_timestamp=now,
        target_timestamp=now,
        current_price=100.0,
        predicted_return=-0.05,
        predicted_price=95.0,
        market_move=-5.0,
        strategy_return=0.05,
        signal="SELL",
        confidence=0.8,
        prediction_score=80.0,
        risk_level="MEDIUM",
        stop_loss_percent=0.03,
        take_profit_percent=0.05,
        risk_reward=1.6667,
        model_version_id="v1",
        model_name="model",
        target_name="future_return_5",
        feature_names=["rsi"],
        raw_prediction=-0.05,
        metadata={
            "factor_ratings": {
                "drawdown_risk": {
                    "value": 0.27,
                    "rating": 4,
                    "label": "elevated",
                },
            },
        },
    )

    saved = adapter.insert_prediction(output)

    assert saved.prediction_run_id == 12
    assert saved.predicted_market_return == Decimal("-0.05")
    assert saved.predicted_strategy_return == Decimal("0.05")
    assert saved.risk_score == Decimal("0.27")
    assert saved.model_results["model_version_id"] == "v1"
