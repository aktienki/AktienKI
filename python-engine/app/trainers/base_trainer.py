from __future__ import annotations

from abc import ABC, abstractmethod
from pathlib import Path
from datetime import datetime


class BaseTrainer(ABC):

    def __init__(self, definition):

        self.definition = definition

        self.alias = definition.alias

        self.scope = definition.scope

        self.timeframe = definition.timeframe

        self.training_window_days = definition.training_window_days

        self.prediction_horizon_minutes = (
            definition.prediction_horizon_minutes
        )

    # --------------------------------------------------------

    @abstractmethod
    def load_data(self):
        """
        Daten laden
        """
        pass

    # --------------------------------------------------------

    @abstractmethod
    def create_features(self, df):
        """
        Features erzeugen
        """
        pass

    # --------------------------------------------------------

    @abstractmethod
    def train(self, df):
        """
        Modell trainieren
        """
        pass

    # --------------------------------------------------------

    @abstractmethod
    def predict(self, features):
        """
        Vorhersage erzeugen
        """
        pass

    # --------------------------------------------------------

    @abstractmethod
    def evaluate(self):
        """
        Modell bewerten
        """
        pass

    # --------------------------------------------------------

    def model_directory(self):

        folder = (
            Path("models")
            / self.alias
            / datetime.now().strftime("%Y%m%d")
        )

        folder.mkdir(
            parents=True,
            exist_ok=True,
        )

        return folder

    # --------------------------------------------------------

    def model_filename(self):

        return (
            self.model_directory()
            / "model.pkl"
        )

    # --------------------------------------------------------

    def metadata_filename(self):

        return (
            self.model_directory()
            / "metadata.json"
        )

    # --------------------------------------------------------

    def feature_directory(self):

        folder = (
            Path("feature_store")
            / self.alias
        )

        folder.mkdir(
            parents=True,
            exist_ok=True,
        )

        return folder

    # --------------------------------------------------------

    def log(self, message):

        print(
            f"[{self.alias}] {message}"
        )