from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from app.config.model_registry import ModelDefinition


@dataclass(slots=True)
class TrainingContext:

    #
    # Model
    #

    alias: str

    scope: str

    timeframe: str

    #
    # Training
    #

    training_window_days: int

    prediction_horizon_minutes: int

    #
    # Runtime
    #

    started_at: datetime

    strategy: object

    definition: ModelDefinition

    #
    # Helpers
    #

    @property
    def is_short_term(self):
        return self.scope == "short_term"

    @property
    def is_long_term(self):
        return self.scope == "long_term"

    @property
    def is_market(self):
        return self.scope == "market"

    @property
    def is_consensus(self):
        return self.scope == "consensus"

    @classmethod
    def from_strategy(cls, strategy):

        return cls(
            alias=strategy.alias,
            scope=str(strategy.scope.value),
            timeframe=str(strategy.interval),
            training_window_days=strategy.training_days,
            prediction_horizon_minutes=strategy.prediction_minutes,
            started_at=datetime.utcnow(),
            strategy=strategy,
            definition=strategy.definition,
        )