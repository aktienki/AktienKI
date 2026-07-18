from __future__ import annotations

from abc import ABC, abstractmethod
from datetime import datetime, timezone
from pathlib import Path
from time import perf_counter
from typing import Any
from uuid import uuid4


class BaseTrainer(ABC):
    """
    Gemeinsame Basisklasse für alle AktienKI-Trainer.

    Verantwortlich für:
    - Modell- und Feature-Pfade
    - Trainings-ID
    - Laufzeitmessung
    - gemeinsame Metadaten
    - Zugriff auf Modellparameter
    """

    def __init__(self, definition: Any) -> None:
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

        self.training_id = uuid4().hex

        self.metrics: dict[str, Any] = {}

        self._training_started: float | None = None
        self._training_finished_at: datetime | None = None

    @property
    def parameters(self) -> dict[str, Any]:
        """
        Modellparameter aus der ModelDefinition.
        """

        parameters = getattr(
            self.definition,
            "parameters",
            {},
        )

        if parameters is None:
            return {}

        if not isinstance(parameters, dict):
            raise TypeError(
                "definition.parameters muss ein Dictionary sein."
            )

        return parameters

    @abstractmethod
    def load_data(self):
        """
        Trainingsdaten laden.
        """

        raise NotImplementedError

    @abstractmethod
    def create_features(self, dataframe):
        """
        Features für das Training erzeugen.
        """

        raise NotImplementedError

    @abstractmethod
    def train(self, dataframe):
        """
        Modell trainieren.
        """

        raise NotImplementedError

    @abstractmethod
    def predict(self, features):
        """
        Vorhersage erzeugen.
        """

        raise NotImplementedError

    @abstractmethod
    def evaluate(self):
        """
        Trainiertes Modell bewerten.
        """

        raise NotImplementedError

    def start_training(self) -> None:
        """
        Startet die Laufzeitmessung.
        """

        if self._training_started is not None:
            raise RuntimeError(
                "Das Training wurde bereits gestartet."
            )

        self._training_started = perf_counter()
        self._training_finished_at = None

        self.metrics.pop(
            "training_seconds",
            None,
        )

    def finish_training(
        self,
        *,
        rows_processed: int | None = None,
    ) -> float:
        """
        Beendet die Laufzeitmessung und speichert Laufzeitmetriken.
        """

        if self._training_started is None:
            raise RuntimeError(
                "start_training() wurde nicht aufgerufen."
            )

        training_seconds = (
            perf_counter() - self._training_started
        )

        training_seconds = round(
            training_seconds,
            4,
        )

        self._training_finished_at = datetime.now(
            timezone.utc
        )

        self.metrics["training_seconds"] = training_seconds

        if rows_processed is not None:
            if rows_processed < 0:
                raise ValueError(
                    "rows_processed darf nicht negativ sein."
                )

            self.metrics["rows_processed"] = rows_processed

            self.metrics["rows_per_second"] = round(
                rows_processed / training_seconds,
                2,
            ) if training_seconds > 0 else None

        self._training_started = None

        return training_seconds

    def add_metrics(
        self,
        metrics: dict[str, Any],
    ) -> None:
        """
        Ergänzt oder aktualisiert Trainingsmetriken.
        """

        if not isinstance(metrics, dict):
            raise TypeError(
                "metrics muss ein Dictionary sein."
            )

        self.metrics.update(metrics)

    def training_metadata(self) -> dict[str, Any]:
        """
        Liefert gemeinsame Metadaten für Artefakte und Reports.
        """

        scope_value = getattr(
            self.scope,
            "value",
            self.scope,
        )

        return {
            "training_id": self.training_id,
            "alias": self.alias,
            "scope": scope_value,
            "timeframe": self.timeframe,
            "training_window_days": (
                self.training_window_days
            ),
            "prediction_horizon_minutes": (
                self.prediction_horizon_minutes
            ),
            "parameters": self.parameters,
            "metrics": dict(self.metrics),
            "finished_at": (
                self._training_finished_at.isoformat()
                if self._training_finished_at
                else None
            ),
        }

    def save_metrics(self) -> dict[str, Any]:
        """
        Kompatibler Alias für bestehende Trainer.
        """

        return self.training_metadata()

    def model_directory(self) -> Path:
        folder = (
            Path("models")
            / self._safe_path_part(self.alias)
            / datetime.now(timezone.utc).strftime(
                "%Y%m%d"
            )
        )

        folder.mkdir(
            parents=True,
            exist_ok=True,
        )

        return folder

    def model_filename(self) -> Path:
        return self.model_directory() / "model.pkl"

    def metadata_filename(self) -> Path:
        return self.model_directory() / "metadata.json"

    def feature_directory(self) -> Path:
        folder = (
            Path("feature_store")
            / self._safe_path_part(self.alias)
        )

        folder.mkdir(
            parents=True,
            exist_ok=True,
        )

        return folder

    def log(
        self,
        message: str,
    ) -> None:
        """
        Einfache kompatible Konsolenausgabe.
        """

        print(
            f"[{self.alias}] {message}"
        )

    @staticmethod
    def _safe_path_part(
        value: Any,
    ) -> str:
        """
        Verhindert ungültige oder unsichere Pfadbestandteile.
        """

        safe_value = str(value).strip()

        for character in (
            "/",
            "\\",
            ":",
            "*",
            "?",
            '"',
            "<",
            ">",
            "|",
        ):
            safe_value = safe_value.replace(
                character,
                "_",
            )

        if not safe_value:
            raise ValueError(
                "Der Pfadbestandteil darf nicht leer sein."
            )

        return safe_value