from __future__ import annotations

from datetime import datetime, timezone

import pandas as pd
import pytest

from app.repositories.feature_store_repository import (
    FeatureStoreRepository,
)


class FakeMappingsResult:
    def __init__(self, rows):
        self._rows = rows

    def all(self):
        return self._rows


class FakeExecutionResult:
    def __init__(self, rows):
        self._rows = rows

    def mappings(self):
        return FakeMappingsResult(self._rows)


class FakeSession:
    def __init__(self, rows):
        self.rows = rows
        self.last_query = None
        self.last_parameters = None

    def execute(self, query, parameters):
        self.last_query = str(query)
        self.last_parameters = parameters
        return FakeExecutionResult(self.rows)


def _row(bar_time, close, target):
    return {
        "bar_time": bar_time,
        "close": close,
        "volume": 1_000_000,
        "rsi_14": 55.0,
        "ema_20": close * 0.99,
        "ema_50": close * 0.97,
        "ema_200": close * 0.90,
        "macd": 1.25,
        "atr_14": 2.50,
        "volatility_20": 0.18,
        "target": target,
    }


def test_load_training_data_returns_sorted_clean_frame():
    session = FakeSession(
        [
            _row(
                datetime(2026, 1, 3, tzinfo=timezone.utc),
                103.0,
                0.03,
            ),
            _row(
                datetime(2026, 1, 1, tzinfo=timezone.utc),
                100.0,
                0.01,
            ),
            _row(
                datetime(2026, 1, 2, tzinfo=timezone.utc),
                101.0,
                0.02,
            ),
        ]
    )

    repository = FeatureStoreRepository(session)

    frame = repository.load_training_data(
        instrument_id=1,
        interval="1d",
        feature_version="1.0.0",
        target_name="target_return_5d",
    )

    assert isinstance(frame, pd.DataFrame)
    assert len(frame) == 3
    assert frame["bar_time"].is_monotonic_increasing
    assert list(frame["close"]) == [100.0, 101.0, 103.0]

    for column in repository.MARKET_FEATURE_COLUMNS:
        assert column in frame.columns
        assert (frame[column] == 0.0).all()

    assert session.last_parameters == {
        "instrument_id": 1,
        "interval": "1d",
        "feature_version": "1.0.0",
    }


def test_empty_result_returns_expected_columns():
    repository = FeatureStoreRepository(FakeSession([]))

    frame = repository.load_training_data(
        instrument_id=1,
        interval="1d",
        feature_version="1.0.0",
        target_name="target_return_5d",
    )

    assert frame.empty
    assert list(frame.columns) == [
        "bar_time",
        *repository.FEATURE_COLUMNS,
        "target",
    ]


def test_invalid_target_is_rejected():
    repository = FeatureStoreRepository(FakeSession([]))

    with pytest.raises(
        ValueError,
        match="Nicht unterstütztes Target",
    ):
        repository.load_training_data(
            instrument_id=1,
            interval="1d",
            feature_version="1.0.0",
            target_name="target_return_60d",
        )
