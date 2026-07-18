from __future__ import annotations

import json
from copy import deepcopy
from typing import Any

from sqlalchemy import text


SYSTEM_PROFILES: tuple[dict[str, Any], ...] = (
    {
        "code": "AKI-HORIZON",
        "name": "AKI-HORIZON",
        "description": (
            "Langfristiges Aktienmodell mit zehn Jahren "
            "Tageshistorie."
        ),
        "scope": "system",
        "status": "active",
        "target_type": "future_return",
        "target_horizon_days": 20,
        "interval": "1d",
        "history_years": 10,
        "retraining_interval_days": 30,
        "configuration": {
            "features": {
                "ema": [20, 50, 200],
                "rsi": 14,
                "macd": [12, 26, 9],
                "atr": 14,
            },
            "training": {
                "algorithm": "ensemble",
                "selector": "horizon_v1",
                "retraining": "monthly",
            },
            "prediction": {
                "confidence_threshold": 0.65,
            },
        },
        "allowed_algorithms": [
            "xgboost",
            "lightgbm",
            "catboost",
        ],
        "version": 1,
    },
    {
        "code": "AKI-PULSE",
        "name": "AKI-PULSE",
        "description": (
            "Kurzfristiges Aktienmodell mit Stundenhistorie."
        ),
        "scope": "system",
        "status": "active",
        "target_type": "future_return",
        "target_horizon_days": 1,
        "interval": "1h",
        "history_years": 3,
        "retraining_interval_days": 7,
        "configuration": {
            "features": {
                "ema": [12, 26, 50],
                "rsi": 14,
                "macd": [12, 26, 9],
                "atr": 14,
            },
            "training": {
                "algorithm": "ensemble",
                "selector": "pulse_v1",
                "retraining": "weekly",
            },
            "prediction": {
                "confidence_threshold": 0.65,
            },
        },
        "allowed_algorithms": [
            "xgboost",
            "lightgbm",
            "catboost",
        ],
        "version": 1,
    },
)


class BootstrapService:
    """
    Legt reproduzierbare Systemdaten an.

    Der Vorgang ist idempotent:
    vorhandene Systemprofile werden auf den definierten
    Systemstand aktualisiert, fehlende Profile werden angelegt.
    """

    def __init__(self, session_factory) -> None:
        self.session_factory = session_factory

    def run(self) -> dict[str, Any]:
        seeded_codes: list[str] = []

        with self.session_factory() as session:
            try:
                for profile in SYSTEM_PROFILES:
                    self._upsert_strategy_profile(
                        session,
                        profile,
                    )
                    seeded_codes.append(profile["code"])

                session.commit()
            except Exception:
                session.rollback()
                raise

        return {
            "strategy_profiles": seeded_codes,
            "count": len(seeded_codes),
        }

    @staticmethod
    def _upsert_strategy_profile(
        session,
        profile: dict[str, Any],
    ) -> None:
        parameters = deepcopy(profile)
        parameters["configuration"] = json.dumps(
            parameters["configuration"]
        )
        parameters["allowed_algorithms"] = json.dumps(
            parameters["allowed_algorithms"]
        )

        session.execute(
            text(
                """
                INSERT INTO strategy_profiles (
                    owner_user_id,
                    code,
                    name,
                    description,
                    scope,
                    status,
                    target_type,
                    target_horizon_days,
                    interval,
                    history_years,
                    retraining_interval_days,
                    configuration,
                    allowed_algorithms,
                    version,
                    is_active,
                    created_at,
                    updated_at,
                    deleted_at
                )
                VALUES (
                    NULL,
                    :code,
                    :name,
                    :description,
                    :scope,
                    :status,
                    :target_type,
                    :target_horizon_days,
                    :interval,
                    :history_years,
                    :retraining_interval_days,
                    CAST(:configuration AS jsonb),
                    CAST(:allowed_algorithms AS jsonb),
                    :version,
                    true,
                    NOW(),
                    NOW(),
                    NULL
                )
                ON CONFLICT (code)
                DO UPDATE SET
                    name = EXCLUDED.name,
                    description = EXCLUDED.description,
                    scope = EXCLUDED.scope,
                    status = EXCLUDED.status,
                    target_type = EXCLUDED.target_type,
                    target_horizon_days = EXCLUDED.target_horizon_days,
                    interval = EXCLUDED.interval,
                    history_years = EXCLUDED.history_years,
                    retraining_interval_days =
                        EXCLUDED.retraining_interval_days,
                    configuration = EXCLUDED.configuration,
                    allowed_algorithms = EXCLUDED.allowed_algorithms,
                    version = EXCLUDED.version,
                    is_active = true,
                    updated_at = NOW(),
                    deleted_at = NULL
                """
            ),
            parameters,
        )
