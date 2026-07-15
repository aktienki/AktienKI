from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import numpy as np
import pandas as pd

from app.training.ensemble_trainer import (
    EnsembleTrainer,
    EnsembleTrainingResult,
)


@dataclass(slots=True)
class TargetTrainingResult:
    target_name: str
    training_result: EnsembleTrainingResult
    prediction: float


@dataclass(slots=True)
class MultiTargetTrainingResult:
    targets: dict[str, TargetTrainingResult]
    decision_payload: dict[str, float | str]


class MultiTargetTrainingEngine:
    """
    Trainiert für jedes numerische AKI-Target ein eigenes Ensemble.

    Unterstützte Standardziele:
    - target_return
    - target_probability
    - target_confidence
    - target_max_gain
    - target_max_loss
    - target_risk_reward
    - target_volatility

    target_direction wird bewusst nicht als Regression trainiert.
    Die Richtung wird aus target_return abgeleitet.
    """

    DEFAULT_TARGETS = [
        "target_return",
        "target_probability",
        "target_confidence",
        "target_max_gain",
        "target_max_loss",
        "target_risk_reward",
        "target_volatility",
    ]

    def __init__(
        self,
        *,
        targets: list[str] | None = None,
        train_size: float = 0.80,
    ) -> None:
        self.targets = (
            targets
            if targets is not None
            else self.DEFAULT_TARGETS.copy()
        )

        if not 0.50 <= train_size < 1.0:
            raise ValueError(
                "train_size muss zwischen 0.50 und kleiner 1.0 liegen."
            )

        self.train_size = train_size

    def train(
        self,
        *,
        dataframe: pd.DataFrame,
        feature_names: list[str],
    ) -> MultiTargetTrainingResult:
        self._validate_input(
            dataframe=dataframe,
            feature_names=feature_names,
        )

        clean = dataframe[
            [
                *feature_names,
                *self.targets,
            ]
        ].replace(
            [np.inf, -np.inf],
            np.nan,
        ).dropna()

        if len(clean) < 300:
            raise ValueError(
                "Zu wenige vollständige Zeilen für Multi-Target-Training: "
                f"{len(clean)}"
            )

        split_index = int(
            len(clean)
            * self.train_size
        )

        x_train = clean[
            feature_names
        ].iloc[:split_index]

        x_validation = clean[
            feature_names
        ].iloc[split_index:]

        latest_features = clean[
            feature_names
        ].tail(1)

        target_results: dict[
            str,
            TargetTrainingResult,
        ] = {}

        for target_name in self.targets:
            y_train = clean[
                target_name
            ].iloc[:split_index]

            y_validation = clean[
                target_name
            ].iloc[split_index:]

            training_result = EnsembleTrainer().train(
                x_train=x_train,
                y_train=y_train,
                x_validation=x_validation,
                y_validation=y_validation,
            )

            model = self._model_from_result(
                training_result
            )

            prediction = float(
                np.asarray(
                    training_result.winner_adapter.predict(
                        model,
                        latest_features,
                    ),
                    dtype=float,
                ).reshape(-1)[0]
            )

            target_results[target_name] = (
                TargetTrainingResult(
                    target_name=target_name,
                    training_result=training_result,
                    prediction=prediction,
                )
            )

        return_prediction = target_results[
            "target_return"
        ].prediction

        decision_payload = {
            "direction": (
                "long"
                if return_prediction > 0
                else "short"
                if return_prediction < 0
                else "neutral"
            ),
            "return": return_prediction,
            "probability": self._clip(
                target_results[
                    "target_probability"
                ].prediction,
                0.0,
                1.0,
            ),
            "confidence": self._clip(
                target_results[
                    "target_confidence"
                ].prediction,
                0.0,
                1.0,
            ),
            "max_gain": max(
                0.0,
                target_results[
                    "target_max_gain"
                ].prediction,
            ),
            "max_loss": min(
                0.0,
                target_results[
                    "target_max_loss"
                ].prediction,
            ),
            "risk_reward": max(
                0.0,
                target_results[
                    "target_risk_reward"
                ].prediction,
            ),
            "volatility": max(
                0.0,
                target_results[
                    "target_volatility"
                ].prediction,
            ),
        }

        return MultiTargetTrainingResult(
            targets=target_results,
            decision_payload=decision_payload,
        )

    def _validate_input(
        self,
        *,
        dataframe: pd.DataFrame,
        feature_names: list[str],
    ) -> None:
        if dataframe.empty:
            raise ValueError(
                "Der DataFrame darf nicht leer sein."
            )

        if not feature_names:
            raise ValueError(
                "feature_names darf nicht leer sein."
            )

        required = {
            *feature_names,
            *self.targets,
        }

        missing = sorted(
            required.difference(
                dataframe.columns
            )
        )

        if missing:
            raise ValueError(
                "Folgende Spalten fehlen für das Multi-Target-Training: "
                f"{missing}"
            )

    @staticmethod
    def _model_from_result(
        result: EnsembleTrainingResult,
    ) -> Any:
        training_result = (
            result.winner_training_result
        )

        if hasattr(
            training_result,
            "model",
        ):
            return training_result.model

        if isinstance(
            training_result,
            dict,
        ) and "model" in training_result:
            return training_result["model"]

        raise RuntimeError(
            "Das Trainingsergebnis enthält kein zugängliches Modell."
        )

    @staticmethod
    def _clip(
        value: float,
        minimum: float,
        maximum: float,
    ) -> float:
        return max(
            minimum,
            min(
                maximum,
                float(value),
            ),
        )


multi_target_training_engine = MultiTargetTrainingEngine()
