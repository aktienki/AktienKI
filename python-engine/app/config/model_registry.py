from dataclasses import dataclass
from typing import Dict

from app.enums.model_scope import ModelScope
from app.enums.timeframe import TimeFrame


@dataclass(slots=True)
class ModelDefinition:

    alias: str

    name: str

    scope: ModelScope

    timeframe: TimeFrame

    training_window_days: int

    prediction_horizon_minutes: int

    enabled: bool = True

    description: str = ""


MODEL_REGISTRY: Dict[str, ModelDefinition] = {

    #
    # ----------------------------------------------------------
    # AKI-PULSE
    # Short-Term Intelligence
    # ----------------------------------------------------------
    #

    "AKI-PULSE": ModelDefinition(

        alias="AKI-PULSE",

        name="AKI Pulse",

        scope=ModelScope.SHORT_TERM,

        timeframe=TimeFrame.H1,

        training_window_days=730,

        prediction_horizon_minutes=1440,

        description="Short-Term Intelligence",

    ),

    #
    # ----------------------------------------------------------
    # AKI-HORIZON
    # Long-Term Intelligence
    # ----------------------------------------------------------
    #

    "AKI-HORIZON": ModelDefinition(

        alias="AKI-HORIZON",

        name="AKI Horizon",

        scope=ModelScope.LONG_TERM,

        timeframe=TimeFrame.D1,

        training_window_days=3650,

        prediction_horizon_minutes=28800,

        description="Long-Term Intelligence",

    ),

    #
    # ----------------------------------------------------------
    # AKI-CLIMATE
    # Market Intelligence
    # ----------------------------------------------------------
    #

    "AKI-CLIMATE": ModelDefinition(

        alias="AKI-CLIMATE",

        name="AKI Climate",

        scope=ModelScope.MARKET,

        timeframe=TimeFrame.D1,

        training_window_days=3650,

        prediction_horizon_minutes=28800,

        description="Market Intelligence",

    ),

    #
    # ----------------------------------------------------------
    # AKI-NEXUS
    # Meta Consensus
    # ----------------------------------------------------------
    #

    "AKI-NEXUS": ModelDefinition(

        alias="AKI-NEXUS",

        name="AKI Nexus",

        scope=ModelScope.CONSENSUS,

        timeframe=TimeFrame.D1,

        training_window_days=0,

        prediction_horizon_minutes=0,

        description="Consensus Engine",

    ),

}


def get_model(alias: str) -> ModelDefinition:

    if alias not in MODEL_REGISTRY:
        raise KeyError(f"Unknown model alias: {alias}")

    return MODEL_REGISTRY[alias]


def get_models_by_scope(scope: ModelScope):

    return [

        model

        for model in MODEL_REGISTRY.values()

        if model.scope == scope

    ]


def get_enabled_models():

    return [

        model

        for model in MODEL_REGISTRY.values()

        if model.enabled

    ]