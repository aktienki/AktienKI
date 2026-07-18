from __future__ import annotations

import json

import pytest

from app.core.bootstrap_service import (
    BootstrapService,
    SYSTEM_PROFILES,
)


class FakeSession:
    def __init__(self, *, fail_on_call: int | None = None):
        self.fail_on_call = fail_on_call
        self.calls = []
        self.commits = 0
        self.rollbacks = 0

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, traceback):
        return False

    def execute(self, statement, parameters):
        self.calls.append(
            {
                "sql": str(statement),
                "parameters": parameters,
            }
        )

        if (
            self.fail_on_call is not None
            and len(self.calls) == self.fail_on_call
        ):
            raise RuntimeError("Bootstrap fehlgeschlagen.")

    def commit(self):
        self.commits += 1

    def rollback(self):
        self.rollbacks += 1


class FakeSessionFactory:
    def __init__(self, session):
        self.session = session

    def __call__(self):
        return self.session


def test_bootstrap_creates_both_system_profiles():
    session = FakeSession()
    service = BootstrapService(
        FakeSessionFactory(session)
    )

    result = service.run()

    assert result == {
        "strategy_profiles": [
            "AKI-HORIZON",
            "AKI-PULSE",
        ],
        "count": 2,
    }
    assert len(session.calls) == 2
    assert session.commits == 1
    assert session.rollbacks == 0

    horizon = session.calls[0]["parameters"]
    pulse = session.calls[1]["parameters"]

    assert horizon["interval"] == "1d"
    assert horizon["history_years"] == 10
    assert horizon["target_horizon_days"] == 20

    assert pulse["interval"] == "1h"
    assert pulse["history_years"] == 3
    assert pulse["target_horizon_days"] == 1


def test_bootstrap_serializes_json_fields():
    session = FakeSession()

    BootstrapService(
        FakeSessionFactory(session)
    ).run()

    for call in session.calls:
        parameters = call["parameters"]

        assert isinstance(
            parameters["configuration"],
            str,
        )
        assert isinstance(
            parameters["allowed_algorithms"],
            str,
        )

        configuration = json.loads(
            parameters["configuration"]
        )
        algorithms = json.loads(
            parameters["allowed_algorithms"]
        )

        assert "features" in configuration
        assert algorithms == [
            "xgboost",
            "lightgbm",
            "catboost",
        ]


def test_bootstrap_is_idempotent_by_using_on_conflict():
    session = FakeSession()

    BootstrapService(
        FakeSessionFactory(session)
    ).run()

    for call in session.calls:
        assert "ON CONFLICT (code)" in call["sql"]
        assert "DO UPDATE SET" in call["sql"]


def test_bootstrap_rolls_back_on_failure():
    session = FakeSession(fail_on_call=2)

    with pytest.raises(
        RuntimeError,
        match="Bootstrap fehlgeschlagen",
    ):
        BootstrapService(
            FakeSessionFactory(session)
        ).run()

    assert session.commits == 0
    assert session.rollbacks == 1


def test_system_profile_codes_are_unique():
    codes = [
        profile["code"]
        for profile in SYSTEM_PROFILES
    ]

    assert len(codes) == len(set(codes))
