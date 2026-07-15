from __future__ import annotations

from abc import ABC
from abc import abstractmethod
from pathlib import Path

from app.config.model_registry import ModelDefinition


class BaseStrategy(ABC):

    def __init__(self, definition: ModelDefinition):

        self.definition = definition

        self.alias = definition.alias

        self.scope = definition.scope

        self.timeframe = definition.timeframe

        self.training_window_days = (
            definition.training_window_days
        )

        self.prediction_horizon_minutes = (
            definition.prediction_horizon_minutes
        )

    # ---------------------------------------------------------

    @property
    def model_directory(self) -> Path:

        folder = (
            Path("storage")
            / "models"
            / self.alias.lower()
        )

        folder.mkdir(
            parents=True,
            exist_ok=True,
        )

        return folder

    # ---------------------------------------------------------

    @property
    def feature_directory(self) -> Path:

        folder = (
            Path("storage")
            / "features"
            / self.alias.lower()
        )

        folder.mkdir(
            parents=True,
            exist_ok=True,
        )

        return folder

    # ---------------------------------------------------------

    @property
    def cache_directory(self) -> Path:

        folder = (
            Path("storage")
            / "cache"
            / self.alias.lower()
        )

        folder.mkdir(
            parents=True,
            exist_ok=True,
        )

        return folder

    # ---------------------------------------------------------

    @property
    def training_days(self) -> int:

        return self.training_window_days

    # ---------------------------------------------------------

    @property
    def prediction_minutes(self) -> int:

        return self.prediction_horizon_minutes

    # ---------------------------------------------------------

    @property
    def interval(self) -> str:

        return self.timeframe.value

    # ---------------------------------------------------------

    @abstractmethod
    def create_features(self, dataframe):

        pass

    # ---------------------------------------------------------

    @abstractmethod
    def create_target(self, dataframe):

        pass

    # ---------------------------------------------------------

    @abstractmethod
    def train(self, dataframe):

        pass

    # ---------------------------------------------------------

    @abstractmethod
    def predict(self, dataframe):

        pass

    # ---------------------------------------------------------

    @abstractmethod
    def evaluate(self, prediction, truth):

        pass