from __future__ import annotations

import json
from contextlib import AbstractContextManager
from pathlib import Path
from types import SimpleNamespace

import numpy as np
import pandas as pd
import pytest

import app.core.training_engine as training_module
from app.core.training_engine import TrainingEngine


FEATURE_COLUMNS = [
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


def make_frame(rows: int = 320) -> pd.DataFrame:
    index = np.arange(rows, dtype=float)
    close = 100.0 + index * 0.1

    frame = pd.DataFrame(
        {
            "bar_time": pd.date_range(
                "2024-01-01",
                periods=rows,
                freq="D",
                tz="UTC",
            ),
            "close": close,
            "volume": 1_000_000.0 + index,
            "rsi_14": 50.0 + np.sin(index / 10.0),
            "ema_20": close * 0.99,
            "ema_50": close * 0.98,
            "ema_200": close * 0.95,
            "macd": np.sin(index / 7.0),
            "atr_14": 2.0 + index * 0.001,
            "volatility_20": 0.20,
            "market_bull_score": 0.60,
            "market_bear_score": 0.20,
            "market_volatility_score": 0.30,
            "market_liquidity_score": 0.70,
            "market_risk_score": 0.25,
            "market_momentum_score": 0.55,
            "target": np.sin(index / 20.0) * 0.02,
        }
    )

    return frame


class FakeSession(AbstractContextManager):
    def __init__(self) -> None:
        self.commits = 0

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, traceback):
        return False

    def commit(self) -> None:
        self.commits += 1


class FakeSessionFactory:
    def __init__(self) -> None:
        self.sessions: list[FakeSession] = []

    def __call__(self) -> FakeSession:
        session = FakeSession()
        self.sessions.append(session)
        return session


class FakeFeatureStoreRepository:
    FEATURE_COLUMNS = FEATURE_COLUMNS

    def __init__(self, session) -> None:
        self.session = session

    def load_training_data(self, **kwargs) -> pd.DataFrame:
        return make_frame()


class FakeModelRepository:
    completed = False
    failed = False
    saved_payload = None

    def __init__(self, session) -> None:
        self.session = session

    def get_or_create_definition(self, **kwargs) -> int:
        return 11

    def start_training_run(self, **kwargs):
        return 22, "run-public-id"

    def save_trained_model(self, **kwargs) -> int:
        type(self).saved_payload = kwargs
        return 33

    def complete_training_run(self, **kwargs) -> None:
        type(self).completed = True

    def fail_training_run(self, **kwargs) -> None:
        type(self).failed = True


class FakeAdapter:
    def train(self, **kwargs):
        return SimpleNamespace(
            model={"model": "fake"},
            metrics={
                "mae": 0.01,
                "ensemble_score": 0.80,
            },
            feature_importance={
                "close": 0.40,
                "rsi_14": 0.20,
            },
            parameters={
                "max_depth": 4,
            },
        )

    def predict(self, model, frame):
        return np.zeros(len(frame), dtype=float)

    def save(self, model, path: Path) -> None:
        Path(path).write_bytes(b"fake-model-artifact")


class FakeModelFactory:
    @staticmethod
    def available_models() -> list[str]:
        return ["xgboost"]

    @staticmethod
    def is_supported(name: str) -> bool:
        return name == "xgboost"

    @staticmethod
    def create(name: str):
        assert name == "xgboost"
        return FakeAdapter()


class FakeEvaluator:
    @staticmethod
    def evaluate(y_true, y_pred):
        return {
            "mae": 0.01,
            "rmse": 0.02,
            "ensemble_score": 0.90,
        }


@pytest.fixture(autouse=True)
def patch_dependencies(monkeypatch):
    FakeModelRepository.completed = False
    FakeModelRepository.failed = False
    FakeModelRepository.saved_payload = None

    monkeypatch.setattr(
        training_module,
        "FeatureStoreRepository",
        FakeFeatureStoreRepository,
    )
    monkeypatch.setattr(
        training_module,
        "ModelRepository",
        FakeModelRepository,
    )
    monkeypatch.setattr(
        training_module,
        "ModelFactory",
        FakeModelFactory,
    )
    monkeypatch.setattr(
        training_module,
        "RegressionEvaluator",
        FakeEvaluator,
    )


def test_training_engine_completes_and_writes_artifacts(tmp_path):
    session_factory = FakeSessionFactory()

    engine = TrainingEngine(
        session_factory,
        storage_path=tmp_path,
    )

    result = engine.train(
        instrument_id=1,
        algorithm="xgboost",
        interval="1d",
        feature_version="1.0.0",
        target_name="target_return_5d",
    )

    artifact_path = Path(result["artifact_path"])
    metadata_path = Path(result["metadata_path"])

    assert result["training_run_id"] == "run-public-id"
    assert result["trained_model_id"] == 33
    assert result["winner_algorithm"] == "xgboost"
    assert "artifact_path" in result
    assert "winner_algorithm" in result
    assert result["winner_algorithm"] == "xgboost"
    assert result["rows"] == {
        "training": 256,
        "validation": 32,
        "test": 32,
    }

    assert artifact_path.exists()
    assert metadata_path.exists()
    assert FakeModelRepository.completed is True
    assert FakeModelRepository.failed is False

    metadata = json.loads(
        metadata_path.read_text(encoding="utf-8")
    )

    assert metadata["feature_names"] == FEATURE_COLUMNS
    assert metadata["feature_hash"]
    assert metadata["feature_version"] == "1.0.0"


def test_training_engine_rejects_too_few_rows(
    monkeypatch,
    tmp_path,
):
    class SmallFeatureStoreRepository(
        FakeFeatureStoreRepository
    ):
        def load_training_data(self, **kwargs):
            return make_frame(299)

    monkeypatch.setattr(
        training_module,
        "FeatureStoreRepository",
        SmallFeatureStoreRepository,
    )

    engine = TrainingEngine(
        FakeSessionFactory(),
        storage_path=tmp_path,
    )

    with pytest.raises(
        ValueError,
        match="Zu wenig Trainingsdaten",
    ):
        engine.train(
            instrument_id=1,
            algorithm="xgboost",
        )


def test_split_frame_is_strictly_chronological():
    frame = make_frame(320)

    train, validation, test = TrainingEngine._split_frame(
        frame
    )

    assert train["bar_time"].max() < validation["bar_time"].min()
    assert validation["bar_time"].max() < test["bar_time"].min()
    assert len(train) == 256
    assert len(validation) == 32
    assert len(test) == 32
