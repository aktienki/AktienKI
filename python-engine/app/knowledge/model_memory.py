from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime


@dataclass(slots=True)
class ModelMemory:

    symbol: str

    sector: str

    regime: str

    timeframe: str

    model_alias: str

    algorithm: str

    version: str

    direction_accuracy: float

    r2: float

    ensemble_score: float

    feature_count: int

    top_features: list[str]

    trained_at: datetime