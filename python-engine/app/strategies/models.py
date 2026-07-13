from dataclasses import dataclass
from typing import Any

@dataclass(frozen=True, slots=True)
class StrategyInstrument:
    instrument_id: int
    role: str
    alias: str
    parameters: dict[str, Any]

@dataclass(frozen=True, slots=True)
class StrategyProfile:
    id: int
    code: str
    name: str
    target_horizon_days: int
    interval: str
    history_years: int
    version: int
    configuration: dict[str, Any]
    allowed_algorithms: list[str]
    instruments: list[StrategyInstrument]
