from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import math
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
    Robustes Multi-Target-Training für AktienKI.

    Direkt trainiert werden nur fachlich stabile Regressionsziele:

    - target_return
    - target_max_gain
    - target_max_loss
    - target_volatility

    Folgende Werte werden bewusst abgeleitet:

    - direction
    - probability
    - confidence
    - risk_reward

    Dadurch vermeiden wir instabile oder bedeutungslose Modelle für
    Targets, die nahezu immer positiv sind oder extreme Ausreißer
    enthalten.
    """

    DEFAULT_TARGETS = [
        "target_return",
        "target_max_gain",
        "target_max_loss",
        "target_volatility",
    ]

    def __init__(
        self,
        *,
        targets: list[str] | None = None,
        train_size: float = 0.80,
        max_risk_reward: float = 10.0,
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

        if max_risk_reward <= 0:
            raise ValueError(
                "max_risk_reward muss größer als 0 sein."
            )

        self.train_size = train_size
        self.max_risk_reward = max_risk_reward

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

        required_columns = [
            *feature_names,
            *self.targets,
        ]

        clean = (
            dataframe[required_columns]
            .replace(
                [np.inf, -np.inf],
                np.nan,
            )
            .dropna()
            .copy()
        )

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

        max_gain_prediction = max(
            0.0,
            target_results[
                "target_max_gain"
            ].prediction,
        )

        max_loss_prediction = min(
            0.0,
            target_results[
                "target_max_loss"
            ].prediction,
        )

        volatility_prediction = max(
            1e-6,
            target_results[
                "target_volatility"
            ].prediction,
        )

        probability = self._return_probability(
            expected_return=return_prediction,
            volatility=volatility_prediction,
        )

        confidence = self._confidence(
            return_result=target_results[
                "target_return"
            ].training_result,
            probability=probability,
            volatility=volatility_prediction,
        )

        risk_reward = self._risk_reward(
            max_gain=max_gain_prediction,
            max_loss=max_loss_prediction,
        )

        direction = self._direction(
            expected_return=return_prediction,
            volatility=volatility_prediction,
        )

        decision_payload = {
            "direction": direction,
            "return": return_prediction,
            "probability": probability,
            "confidence": confidence,
            "max_gain": max_gain_prediction,
            "max_loss": max_loss_prediction,
            "risk_reward": risk_reward,
            "volatility": volatility_prediction,
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
    def _return_probability(
        *,
        expected_return: float,
        volatility: float,
    ) -> float:
        """
        Wandelt den erwarteten Return relativ zur prognostizierten
        Volatilität in eine Wahrscheinlichkeit von 0 bis 1 um.
        """

        signal_to_noise = (
            expected_return
            / max(
                volatility,
                1e-6,
            )
        )

        signal_to_noise = max(
            -8.0,
            min(
                8.0,
                signal_to_noise,
            ),
        )

        probability = (
            1.0
            / (
                1.0
                + math.exp(
                    -signal_to_noise
                )
            )
        )

        return round(
            probability,
            6,
        )

    @staticmethod
    def _confidence(
        *,
        return_result: EnsembleTrainingResult,
        probability: float,
        volatility: float,
    ) -> float:
        metrics = (
            return_result.winner_metrics
        )

        direction_accuracy = float(
            metrics.get(
                "direction_accuracy",
                0.5,
            )
        )

        r2 = max(
            0.0,
            float(
                metrics.get(
                    "r2",
                    0.0,
                )
            ),
        )

        normalized_rmse = max(
            0.0,
            float(
                metrics.get(
                    "normalized_rmse",
                    1.0,
                )
            ),
        )

        model_quality = (
            max(
                0.0,
                direction_accuracy - 0.5,
            )
            * 2.0
            * 0.50
            + min(
                1.0,
                r2,
            )
            * 0.25
            + (
                1.0
                / (
                    1.0
                    + normalized_rmse
                )
            )
            * 0.25
        )

        signal_strength = min(
            1.0,
            abs(
                probability
                - 0.5
            )
            * 2.0,
        )

        volatility_penalty = (
            1.0
            / (
                1.0
                + volatility
                * 20.0
            )
        )

        confidence = (
            model_quality
            * 0.55
            + signal_strength
            * 0.30
            + volatility_penalty
            * 0.15
        )

        return round(
            max(
                0.0,
                min(
                    1.0,
                    confidence,
                ),
            ),
            6,
        )

    def _risk_reward(
        self,
        *,
        max_gain: float,
        max_loss: float,
    ) -> float:
        downside = abs(
            min(
                0.0,
                max_loss,
            )
        )

        if downside < 1e-6:
            return self.max_risk_reward

        value = (
            max(
                0.0,
                max_gain,
            )
            / downside
        )

        return round(
            max(
                0.0,
                min(
                    self.max_risk_reward,
                    value,
                ),
            ),
            4,
        )

    @staticmethod
    def _direction(
        *,
        expected_return: float,
        volatility: float,
    ) -> str:
        neutral_threshold = max(
            0.001,
            volatility
            * 0.15,
        )

        if expected_return > neutral_threshold:
            return "long"

        if expected_return < -neutral_threshold:
            return "short"

        return "neutral"


multi_target_training_engine = MultiTargetTrainingEngine()
