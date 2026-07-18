from dataclasses import dataclass, field
from enum import Enum
from typing import Any


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

    # Sprint 18
    feature_version: str = "v1"

    target_column: str = "target_return_5d"

    retrain_days: int = 7

    enabled: bool = True

    model_weight: float = 1.0

    parameters: dict[str, Any] = field(default_factory=dict)


MODEL_REGISTRY = {

    "AKI-PULSE": ModelDefinition(
        alias="AKI-PULSE",
        scope=ModelScope.SHORT_TERM,
        timeframe="1h",
        training_window_days=1095,
        prediction_horizon_minutes=1440,
        description="Short-Term Intelligence",

        feature_version="v2",
        target_column="target_return_1d",
        retrain_days=7,
        model_weight=0.90,
    ),

    "AKI-HORIZON": ModelDefinition(
        alias="AKI-HORIZON",
        scope=ModelScope.LONG_TERM,
        timeframe="1d",
        training_window_days=3650,
        prediction_horizon_minutes=28800,
        description="Long-Term Intelligence",

        feature_version="v2",
        target_column="target_return_20d",
        retrain_days=30,
        model_weight=1.00,
    ),

    "AKI-CLIMATE": ModelDefinition(
        alias="AKI-CLIMATE",
        scope=ModelScope.MARKET,
        timeframe="1d",
        training_window_days=3650,
        prediction_horizon_minutes=28800,
        description="Market Intelligence",

        feature_version="v2",
        retrain_days=1,
        model_weight=0.70,
    ),

    "AKI-NEXUS": ModelDefinition(
        alias="AKI-NEXUS",
        scope=ModelScope.CONSENSUS,
        timeframe="multi",
        training_window_days=0,
        prediction_horizon_minutes=0,
        description="Consensus Intelligence",

        feature_version="v2",
        retrain_days=1,
        model_weight=1.20,
    ),
}