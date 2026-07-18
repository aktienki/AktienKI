from __future__ import annotations

from copy import deepcopy
from typing import Any

from sqlalchemy import text

from app.strategies.models import StrategyInstrument, StrategyProfile


DEFAULT_CONFIGURATION: dict[str, Any] = {
    "features": {
        "ema": [20, 50, 200],
        "rsi": 14,
        "macd": [12, 26, 9],
        "atr": 14,
    },
    "training": {
        "algorithm": "ensemble",
        "selector": None,
        "retraining": "manual",
    },
    "prediction": {
        "confidence_threshold": 0.65,
    },
}


class StrategyProfileRepository:
    def __init__(self, session):
        self.session = session

    def get_by_code(self, code: str) -> StrategyProfile | None:
        profile = self.session.execute(
            text(
                """
                SELECT id, code, name, target_horizon_days, interval,
                       history_years, version, configuration,
                       allowed_algorithms
                FROM strategy_profiles
                WHERE code = :code
                  AND is_active = true
                  AND deleted_at IS NULL
                LIMIT 1
                """
            ),
            {"code": code},
        ).mappings().first()

        if profile is None:
            return None

        return self._hydrate_profile(profile)

    def list_active(self) -> list[StrategyProfile]:
        profiles = self.session.execute(
            text(
                """
                SELECT id, code, name, target_horizon_days, interval,
                       history_years, version, configuration,
                       allowed_algorithms
                FROM strategy_profiles
                WHERE is_active = true
                  AND deleted_at IS NULL
                ORDER BY code
                """
            )
        ).mappings().all()

        return [self._hydrate_profile(profile) for profile in profiles]

    def resolve_configuration(
        self,
        profile: StrategyProfile,
    ) -> dict[str, Any]:
        configuration = deepcopy(DEFAULT_CONFIGURATION)

        configuration["market"] = {
            "interval": profile.interval,
            "history_years": profile.history_years,
            "prediction_horizon_days": profile.target_horizon_days,
        }

        self._deep_merge(
            configuration,
            profile.configuration,
        )

        return configuration

    def _hydrate_profile(self, profile) -> StrategyProfile:
        rows = self.session.execute(
            text(
                """
                SELECT instrument_id, role, alias,
                       COALESCE(parameters, '{}'::jsonb) AS parameters
                FROM strategy_profile_instruments
                WHERE strategy_profile_id = :id
                  AND is_enabled = true
                ORDER BY id
                """
            ),
            {"id": profile["id"]},
        ).mappings().all()

        return StrategyProfile(
            id=int(profile["id"]),
            code=str(profile["code"]),
            name=str(profile["name"]),
            target_horizon_days=int(profile["target_horizon_days"]),
            interval=str(profile["interval"]),
            history_years=int(profile["history_years"]),
            version=int(profile["version"]),
            configuration=dict(profile["configuration"] or {}),
            allowed_algorithms=list(profile["allowed_algorithms"] or []),
            instruments=[
                StrategyInstrument(
                    instrument_id=int(row["instrument_id"]),
                    role=str(row["role"]),
                    alias=str(row["alias"]),
                    parameters=dict(row["parameters"] or {}),
                )
                for row in rows
            ],
        )

    @classmethod
    def _deep_merge(
        cls,
        target: dict[str, Any],
        source: dict[str, Any],
    ) -> dict[str, Any]:
        for key, value in source.items():
            if (
                key in target
                and isinstance(target[key], dict)
                and isinstance(value, dict)
            ):
                cls._deep_merge(target[key], value)
            else:
                target[key] = deepcopy(value)

        return target
