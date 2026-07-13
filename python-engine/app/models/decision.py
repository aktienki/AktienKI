from __future__ import annotations

from dataclasses import asdict, dataclass
from typing import Any


@dataclass(frozen=True, slots=True)
class Decision:
    instrument_id: int
    trained_model_id: int
    interval: str
    current_price: float
    predicted_price_5d: float
    price_difference_5d: float
    market_return_5d: float
    long_return_5d: float
    short_return_5d: float
    strategy: str
    strategy_return_5d: float
    direction_score: float
    signal_strength: float
    confidence: float
    risk_score: float
    trend_strength: float
    ai_score: float
    signal: str
    explanation: dict[str, Any]
    metadata: dict[str, Any]

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)
