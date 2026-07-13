from sqlalchemy import text
from app.strategies.models import StrategyInstrument, StrategyProfile

class StrategyProfileRepository:
    def __init__(self, session):
        self.session = session

    def get_by_code(self, code: str):
        profile = self.session.execute(text("""
            SELECT id, code, name, target_horizon_days, interval,
                   history_years, version, configuration, allowed_algorithms
            FROM strategy_profiles
            WHERE code=:code AND is_active=true AND deleted_at IS NULL
            LIMIT 1
        """), {"code": code}).mappings().first()

        if profile is None:
            return None

        rows = self.session.execute(text("""
            SELECT instrument_id, role, alias,
                   COALESCE(parameters, '{}'::jsonb) AS parameters
            FROM strategy_profile_instruments
            WHERE strategy_profile_id=:id AND is_enabled=true
            ORDER BY id
        """), {"id": profile["id"]}).mappings().all()

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
                    instrument_id=int(r["instrument_id"]),
                    role=str(r["role"]),
                    alias=str(r["alias"]),
                    parameters=dict(r["parameters"] or {}),
                ) for r in rows
            ],
        )
