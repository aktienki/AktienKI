from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import numpy as np
import pandas as pd

from app.training.evaluator import RegressionEvaluator
from app.training.factory import ModelFactory


@dataclass(slots=True)
class EnsembleCandidateResult:
    algorithm: str
    adapter: Any
    training_result: Any
    validation_prediction: np.ndarray
    metrics: dict[str, float]


@dataclass(slots=True)
class EnsembleTrainingResult:
    winner_algorithm: str
    winner_adapter: Any
    winner_training_result: Any
    winner_metrics: dict[str, float]
    runner_up_algorithm: str | None
    candidates: list[dict[str, Any]]


class EnsembleTrainer:
    """
    Trainiert alle registrierten Modelladapter und wählt das Modell
    mit dem höchsten Ensemble-Score aus.
    """

    def __init__(
        self,
        *,
        algorithms: list[str] | None = None,
    ) -> None:
        requested = (
            algorithms
            if algorithms is not None
            else ModelFactory.available_models()
        )

        self.algorithms = [
            algorithm.lower().strip()
            for algorithm in requested
            if algorithm and algorithm.strip()
        ]

        unsupported = [
            algorithm
            for algorithm in self.algorithms
            if not ModelFactory.is_supported(algorithm)
        ]

        if unsupported:
            raise ValueError(
                "Nicht unterstützte Ensemble-Algorithmen: "
                f"{unsupported}"
            )

        if not self.algorithms:
            raise ValueError(
                "Für das Ensemble ist kein Modell registriert."
            )

    def train(
        self,
        *,
        x_train: pd.DataFrame,
        y_train: pd.Series,
        x_validation: pd.DataFrame,
        y_validation: pd.Series,
        training_parameters: dict[str, dict[str, Any]] | None = None,
    ) -> EnsembleTrainingResult:
        parameters = training_parameters or {}
        results: list[EnsembleCandidateResult] = []
        errors: dict[str, str] = {}

        for algorithm in self.algorithms:
            try:
                adapter = ModelFactory.create(algorithm)

                training_result = adapter.train(
                    x_train=x_train,
                    y_train=y_train,
                    x_validation=x_validation,
                    y_validation=y_validation,
                    parameters=parameters.get(algorithm, {}),
                )

                prediction = np.asarray(
                    adapter.predict(
                        training_result.model,
                        x_validation,
                    ),
                    dtype=float,
                )

                metrics = RegressionEvaluator.evaluate(
                    actual=y_validation,
                    predicted=prediction,
                )

                results.append(
                    EnsembleCandidateResult(
                        algorithm=algorithm,
                        adapter=adapter,
                        training_result=training_result,
                        validation_prediction=prediction,
                        metrics=metrics,
                    )
                )
            except Exception as exception:
                errors[algorithm] = str(exception)

        if not results:
            details = "; ".join(
                f"{name}: {message}"
                for name, message in errors.items()
            )

            raise RuntimeError(
                "Kein Ensemble-Modell konnte trainiert werden."
                + (f" Fehler: {details}" if details else "")
            )

        results.sort(
            key=lambda item: item.metrics.get(
                "ensemble_score",
                float("-inf"),
            ),
            reverse=True,
        )

        winner = results[0]
        runner_up = (
            results[1].algorithm
            if len(results) > 1
            else None
        )

        candidates = [
            {
                "algorithm": item.algorithm,
                "metrics": item.metrics,
                "rank": index,
                "is_winner": index == 1,
            }
            for index, item in enumerate(
                results,
                start=1,
            )
        ]

        for algorithm, message in errors.items():
            candidates.append(
                {
                    "algorithm": algorithm,
                    "metrics": {},
                    "rank": None,
                    "is_winner": False,
                    "error": message,
                }
            )

        return EnsembleTrainingResult(
            winner_algorithm=winner.algorithm,
            winner_adapter=winner.adapter,
            winner_training_result=winner.training_result,
            winner_metrics=winner.metrics,
            runner_up_algorithm=runner_up,
            candidates=candidates,
        )
