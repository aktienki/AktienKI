from __future__ import annotations

from contextlib import AbstractContextManager
from pathlib import Path
from types import SimpleNamespace

import numpy as np
import pytest

import app.core.prediction_engine as prediction_module
from app.core.prediction_engine import PredictionEngine


FEATURE_NAMES = [
    "close",
    "volume",
    "rsi_14",
    "ema_20",
    "ema_50",
    "ema_200",
    "macd",
    "atr_14",
    "volatility_20",
    "market_bull_score",
    "market_bear_score",
    "market_volatility_score",
    "market_liquidity_score",
    "market_risk_score",
    "market_momentum_score",
]


def make_feature_row() -> dict:
    return {
        "bar_time": "2026-07-18T00:00:00+00:00",
        "close": 100.0,
        "volume": 1_000_000.0,
        "rsi_14": 58.0,
        "ema_20": 99.0,
        "ema_50": 97.0,
        "ema_200": 90.0,
        "macd": 1.2,
        "atr_14": 2.5,
        "volatility_20": 0.20,
        "market_bull_score": 70.0,
        "market_bear_score": 20.0,
        "market_volatility_score": 30.0,
        "market_liquidity_score": 80.0,
        "market_risk_score": 25.0,
        "market_momentum_score": 65.0,
    }


class FakeSession(AbstractContextManager):
    def __init__(self) -> None:
        self.commits = 0
        self.rollbacks = 0

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, traceback):
        return False

    def commit(self) -> None:
        self.commits += 1

    def rollback(self) -> None:
        self.rollbacks += 1


class FakeSessionFactory:
    def __init__(self) -> None:
        self.sessions: list[FakeSession] = []

    def __call__(self) -> FakeSession:
        session = FakeSession()
        self.sessions.append(session)
        return session


class FakeModelLoaderRepository:
    model_info: dict | None = None

    def __init__(self, session) -> None:
        self.session = session

    def latest_ready(self, **kwargs):
        return type(self).model_info


class FakeLatestFeatureRepository:
    feature_row: dict | None = None

    def __init__(self, session) -> None:
        self.session = session

    def latest(self, **kwargs):
        return type(self).feature_row


class FakePredictionRepository:
    saved_decision = None
    prediction_id = 501
    should_fail = False

    def __init__(self, session) -> None:
        self.session = session

    def save(self, decision):
        if type(self).should_fail:
            raise RuntimeError("Speichern fehlgeschlagen.")

        type(self).saved_decision = decision
        return type(self).prediction_id


class FakeModel:
    def __init__(self, predicted_return: float) -> None:
        self.predicted_return = predicted_return
        self.received_columns: list[str] | None = None

    def predict(self, frame):
        self.received_columns = list(frame.columns)
        return np.array([self.predicted_return], dtype=float)


class FakeScorer:
    def score(
        self,
        *,
        market_return,
        confidence,
        volatility,
        trend_strength,
    ):
        return {
            "direction_score": 80.0,
            "signal_strength": 75.0,
            "confidence": confidence,
            "risk_score": 20.0,
            "trend_strength": trend_strength,
            "ai_score": 88.0,
            "signal": "BUY" if market_return >= 0 else "SELL",
        }


class FakeDecision:
    def __init__(self, **kwargs) -> None:
        self.payload = kwargs

    def to_dict(self) -> dict:
        return dict(self.payload)


@pytest.fixture(autouse=True)
def patch_dependencies(monkeypatch, tmp_path):
    artifact_path = tmp_path / "model.joblib"
    artifact_path.write_bytes(b"fake-model")

    FakeModelLoaderRepository.model_info = {
        "id": 77,
        "artifact_path": str(artifact_path),
        "feature_version": "1.0.0",
        "feature_names": FEATURE_NAMES,
        "metrics": {
            "test": {
                "direction_accuracy": 0.80,
                "r2": 0.50,
            }
        },
    }

    FakeLatestFeatureRepository.feature_row = make_feature_row()
    FakePredictionRepository.saved_decision = None
    FakePredictionRepository.should_fail = False

    fake_model = FakeModel(predicted_return=0.05)

    monkeypatch.setattr(
        prediction_module,
        "TrainedModelLoaderRepository",
        FakeModelLoaderRepository,
    )
    monkeypatch.setattr(
        prediction_module,
        "LatestFeatureRepository",
        FakeLatestFeatureRepository,
    )
    monkeypatch.setattr(
        prediction_module,
        "PredictionRepository",
        FakePredictionRepository,
    )
    monkeypatch.setattr(
        prediction_module,
        "Decision",
        FakeDecision,
    )
    monkeypatch.setattr(
        prediction_module.joblib,
        "load",
        lambda path: fake_model,
    )
    monkeypatch.setattr(
        PredictionEngine,
        "__init__",
        lambda self, session_factory: (
            setattr(self, "session_factory", session_factory),
            setattr(self, "scorer", FakeScorer()),
        )[-1],
    )

    return SimpleNamespace(
        artifact_path=artifact_path,
        model=fake_model,
    )


def test_prediction_engine_creates_and_saves_long_prediction(
    patch_dependencies,
):
    session_factory = FakeSessionFactory()
    engine = PredictionEngine(session_factory)

    result = engine.predict(
        instrument_id=1,
        algorithm="xgboost",
        target_name="target_return_5d",
        interval="1d",
    )

    assert result["prediction_id"] == 501
    assert result["instrument_id"] == 1
    assert result["trained_model_id"] == 77
    assert result["strategy"] == "long"
    assert result["current_price"] == pytest.approx(100.0)
    assert result["predicted_price_5d"] == pytest.approx(105.0)
    assert result["price_difference_5d"] == pytest.approx(5.0)
    assert result["market_return_5d"] == pytest.approx(0.05)
    assert result["long_return_5d"] == pytest.approx(0.05)
    assert result["short_return_5d"] == pytest.approx(-0.05)
    assert result["strategy_return_5d"] == pytest.approx(0.05)
    assert result["signal"] == "BUY"
    assert result["ai_score"] == pytest.approx(88.0)

    assert (
        patch_dependencies.model.received_columns
        == FEATURE_NAMES
    )

    assert FakePredictionRepository.saved_decision is not None
    assert session_factory.sessions[-1].commits == 1


def test_prediction_engine_creates_positive_short_strategy_return(
    monkeypatch,
    patch_dependencies,
):
    short_model = FakeModel(predicted_return=-0.04)

    monkeypatch.setattr(
        prediction_module.joblib,
        "load",
        lambda path: short_model,
    )

    engine = PredictionEngine(FakeSessionFactory())

    result = engine.predict(
        instrument_id=1,
    )

    assert result["strategy"] == "short"
    assert result["predicted_price_5d"] == pytest.approx(96.0)
    assert result["price_difference_5d"] == pytest.approx(-4.0)
    assert result["market_return_5d"] == pytest.approx(-0.04)
    assert result["long_return_5d"] == pytest.approx(-0.04)
    assert result["short_return_5d"] == pytest.approx(0.04)
    assert result["strategy_return_5d"] == pytest.approx(0.04)
    assert result["signal"] == "SELL"


def test_prediction_engine_rejects_missing_model():
    FakeModelLoaderRepository.model_info = None

    engine = PredictionEngine(FakeSessionFactory())

    with pytest.raises(
        RuntimeError,
        match="Kein trainiertes Modell gefunden",
    ):
        engine.predict(
            instrument_id=1,
        )


def test_prediction_engine_rejects_missing_feature():
    row = make_feature_row()
    row.pop("rsi_14")
    FakeLatestFeatureRepository.feature_row = row

    engine = PredictionEngine(FakeSessionFactory())

    with pytest.raises(
        RuntimeError,
        match="Fehlende Modell-Features: rsi_14",
    ):
        engine.predict(
            instrument_id=1,
        )


def test_prediction_engine_rolls_back_when_save_fails():
    FakePredictionRepository.should_fail = True
    session_factory = FakeSessionFactory()
    engine = PredictionEngine(session_factory)

    with pytest.raises(
        RuntimeError,
        match="Speichern fehlgeschlagen",
    ):
        engine.predict(
            instrument_id=1,
        )

    assert session_factory.sessions[-1].rollbacks == 1
    assert session_factory.sessions[-1].commits == 0
