from dataclasses import dataclass
from enum import Enum


class ModelScope(str, Enum):
    SHORT_TERM = "short_term"
    LONG_TERM = "long_term"
    MARKET = "market"
    CONSENSUS = "consensus"


@dataclass(slots=True)
class ModelDefinition:
    alias: str
    scope: ModelScope
    timeframe: str
    training_window_days: int
    prediction_horizon_minutes: int
    description: str


MODEL_REGISTRY = {

    "AKI-PULSE": ModelDefinition(
        alias="AKI-PULSE",
        scope=ModelScope.SHORT_TERM,
        timeframe="1h",
        training_window_days=1095,          # 3 Jahre
        prediction_horizon_minutes=1440,    # 24 Stunden
        description="Short-Term Intelligence",
    ),

    "AKI-HORIZON": ModelDefinition(
        alias="AKI-HORIZON",
        scope=ModelScope.LONG_TERM,
        timeframe="1d",
        training_window_days=3650,          # 10 Jahre
        prediction_horizon_minutes=28800,   # 20 Börsentage
        description="Long-Term Intelligence",
    ),

    "AKI-CLIMATE": ModelDefinition(
        alias="AKI-CLIMATE",
        scope=ModelScope.MARKET,
        timeframe="1d",
        training_window_days=3650,
        prediction_horizon_minutes=28800,
        description="Market Intelligence",
    ),

    "AKI-NEXUS": ModelDefinition(
        alias="AKI-NEXUS",
        scope=ModelScope.CONSENSUS,
        timeframe="multi",
        training_window_days=0,
        prediction_horizon_minutes=0,
        description="Consensus Intelligence",
    ),

}