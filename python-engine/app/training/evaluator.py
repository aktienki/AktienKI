from __future__ import annotations

from dataclasses import dataclass
from typing import Sequence

import numpy as np
import pandas as pd
from sklearn.metrics import (
    mean_absolute_error,
    mean_squared_error,
    r2_score,
)

from app.training.optimizer import OptimizerResult, SelectionOptimizer
from app.training.selection_score import SelectionResult, SelectionScore


@dataclass(frozen=True, slots=True)
class CandidateEvaluation:
    name: str
    result: SelectionResult


class RegressionEvaluator:
    @staticmethod
    def evaluate(
        actual: pd.Series | np.ndarray,
        predicted: pd.Series | np.ndarray,
    ) -> dict[str, float]:
        actual_array = np.asarray(actual, dtype=float)
        predicted_array = np.asarray(predicted, dtype=float)

        if actual_array.shape != predicted_array.shape:
            raise ValueError(
                "actual und predicted müssen dieselbe Form besitzen."
            )

        if actual_array.size == 0:
            raise ValueError(
                "Es wurden keine Werte zur Auswertung übergeben."
            )

        if not np.all(np.isfinite(actual_array)):
            raise ValueError("actual enthält ungültige Werte.")

        if not np.all(np.isfinite(predicted_array)):
            raise ValueError("predicted enthält ungültige Werte.")

        return {
            "mae": float(
                mean_absolute_error(
                    actual_array,
                    predicted_array,
                )
            ),
            "rmse": float(
                mean_squared_error(
                    actual_array,
                    predicted_array,
                ) ** 0.5
            ),
            "r2": float(
                r2_score(
                    actual_array,
                    predicted_array,
                )
            ),
            "direction_accuracy": float(
                np.mean(
                    np.sign(actual_array)
                    == np.sign(predicted_array)
                )
            ),
        }


class StrategyEvaluator:
    def __init__(
        self,
        *,
        selection_score: SelectionScore | None = None,
        optimizer: SelectionOptimizer | None = None,
    ) -> None:
        self.selection_score = (
            selection_score or SelectionScore()
        )
        self.optimizer = optimizer or SelectionOptimizer()

    def evaluate(
        self,
        returns: Sequence[float],
        equity_curve: Sequence[float],
        *,
        periods_per_year: int = 252,
    ) -> SelectionResult:
        return self.selection_score.calculate(
            returns=returns,
            equity_curve=equity_curve,
            periods_per_year=periods_per_year,
        )

    def evaluate_candidate(
        self,
        name: str,
        returns: Sequence[float],
        equity_curve: Sequence[float],
        *,
        periods_per_year: int = 252,
    ) -> CandidateEvaluation:
        return CandidateEvaluation(
            name=name,
            result=self.evaluate(
                returns=returns,
                equity_curve=equity_curve,
                periods_per_year=periods_per_year,
            ),
        )

    def select_best(
        self,
        candidates: Sequence[SelectionResult],
    ) -> OptimizerResult:
        return self.optimizer.select_best(candidates)

    def rank(
        self,
        candidates: Sequence[SelectionResult],
    ) -> list[SelectionResult]:
        return self.optimizer.rank(candidates)