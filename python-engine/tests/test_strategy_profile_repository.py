from __future__ import annotations

from app.repositories.strategy_profile_repository import (
    StrategyProfileRepository,
)
from app.strategies.models import StrategyInstrument, StrategyProfile


class FakeMappings:
    def __init__(self, rows):
        self.rows = rows

    def first(self):
        return self.rows[0] if self.rows else None

    def all(self):
        return list(self.rows)


class FakeResult:
    def __init__(self, rows):
        self.rows = rows

    def mappings(self):
        return FakeMappings(self.rows)


class FakeSession:
    def __init__(self, results):
        self.results = list(results)
        self.calls = []

    def execute(self, statement, params=None):
        self.calls.append(
            {
                "sql": str(statement),
                "params": params,
            }
        )
        return FakeResult(self.results.pop(0))


def profile_row(
    *,
    profile_id=1,
    code="AKI-HORIZON",
    interval="1d",
    history_years=10,
    target_horizon_days=20,
    configuration=None,
):
    return {
        "id": profile_id,
        "code": code,
        "name": code,
        "target_horizon_days": target_horizon_days,
        "interval": interval,
        "history_years": history_years,
        "version": 1,
        "configuration": configuration or {},
        "allowed_algorithms": ["xgboost", "random_forest"],
    }


def test_get_by_code_loads_profile_and_instruments():
    session = FakeSession(
        [
            [profile_row()],
            [
                {
                    "instrument_id": 11,
                    "role": "primary",
                    "alias": "AAPL",
                    "parameters": {"weight": 1.0},
                }
            ],
        ]
    )

    repository = StrategyProfileRepository(session)

    profile = repository.get_by_code("AKI-HORIZON")

    assert profile is not None
    assert profile.code == "AKI-HORIZON"
    assert profile.interval == "1d"
    assert profile.history_years == 10
    assert profile.target_horizon_days == 20
    assert profile.instruments == [
        StrategyInstrument(
            instrument_id=11,
            role="primary",
            alias="AAPL",
            parameters={"weight": 1.0},
        )
    ]


def test_list_active_loads_all_profiles():
    session = FakeSession(
        [
            [
                profile_row(),
                profile_row(
                    profile_id=2,
                    code="AKI-PULSE",
                    interval="1h",
                    history_years=3,
                    target_horizon_days=1,
                ),
            ],
            [],
            [],
        ]
    )

    repository = StrategyProfileRepository(session)

    profiles = repository.list_active()

    assert [profile.code for profile in profiles] == [
        "AKI-HORIZON",
        "AKI-PULSE",
    ]
    assert profiles[1].interval == "1h"
    assert profiles[1].history_years == 3


def test_resolve_configuration_adds_defaults_and_profile_market_values():
    profile = StrategyProfile(
        id=2,
        code="AKI-PULSE",
        name="AKI-PULSE",
        target_horizon_days=1,
        interval="1h",
        history_years=3,
        version=1,
        configuration={
            "features": {
                "ema": [12, 26, 50],
                "rsi": 10,
            },
            "prediction": {
                "confidence_threshold": 0.72,
            },
        },
        allowed_algorithms=["xgboost"],
        instruments=[],
    )

    repository = StrategyProfileRepository(FakeSession([]))

    resolved = repository.resolve_configuration(profile)

    assert resolved["market"] == {
        "interval": "1h",
        "history_years": 3,
        "prediction_horizon_days": 1,
    }
    assert resolved["features"]["ema"] == [12, 26, 50]
    assert resolved["features"]["rsi"] == 10
    assert resolved["features"]["macd"] == [12, 26, 9]
    assert resolved["features"]["atr"] == 14
    assert resolved["prediction"]["confidence_threshold"] == 0.72
    assert resolved["training"]["retraining"] == "manual"


def test_resolve_configuration_does_not_mutate_profile_configuration():
    original = {
        "features": {
            "rsi": 9,
        }
    }

    profile = StrategyProfile(
        id=1,
        code="TEST",
        name="TEST",
        target_horizon_days=5,
        interval="1d",
        history_years=10,
        version=1,
        configuration=original,
        allowed_algorithms=[],
        instruments=[],
    )

    repository = StrategyProfileRepository(FakeSession([]))
    resolved = repository.resolve_configuration(profile)

    resolved["features"]["rsi"] = 99

    assert profile.configuration["features"]["rsi"] == 9
