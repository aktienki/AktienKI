from __future__ import annotations

import math

import numpy as np
import pandas as pd
from sklearn.metrics import (
    mean_absolute_error,
    mean_squared_error,
    r2_score,
)


class RegressionEvaluator:
    """
    Bewertet Regressionsmodelle für AktienKI.

    Der Ensemble-Score berücksichtigt:

    - Richtungsgenauigkeit gegenüber einer 50-%-Baseline
    - R² als Erklärungskraft
    - RMSE relativ zur Streuung des echten Targets
    - eine Qualitätsstrafe bei negativem R²

    Dadurch kann ein Modell mit stark negativem R² nicht allein durch
    einen numerisch kleinen absoluten RMSE zum Champion werden.
    """

    @staticmethod
    def evaluate(
        actual: pd.Series | np.ndarray,
        predicted: pd.Series | np.ndarray,
    ) -> dict[str, float]:
        actual_array = np.asarray(
            actual,
            dtype=float,
        ).reshape(-1)

        predicted_array = np.asarray(
            predicted,
            dtype=float,
        ).reshape(-1)

        RegressionEvaluator._validate_arrays(
            actual_array,
            predicted_array,
        )

        mae = float(
            mean_absolute_error(
                actual_array,
                predicted_array,
            )
        )

        rmse = float(
            mean_squared_error(
                actual_array,
                predicted_array,
            ) ** 0.5
        )

        r2 = float(
            r2_score(
                actual_array,
                predicted_array,
            )
        )

        direction_accuracy = float(
            np.mean(
                np.sign(actual_array)
                == np.sign(predicted_array)
            )
        )

        target_std = float(
            np.std(
                actual_array,
                ddof=0,
            )
        )

        normalized_rmse = (
            rmse / target_std
            if target_std > 1e-12
            else float("inf")
        )

        ensemble_score = RegressionEvaluator.ensemble_score(
            normalized_rmse=normalized_rmse,
            r2=r2,
            direction_accuracy=direction_accuracy,
        )

        return {
            "mae": mae,
            "rmse": rmse,
            "normalized_rmse": normalized_rmse,
            "r2": r2,
            "direction_accuracy": direction_accuracy,
            "ensemble_score": ensemble_score,
        }

    @staticmethod
    def ensemble_score(
        *,
        normalized_rmse: float,
        r2: float,
        direction_accuracy: float,
    ) -> float:
        """
        Liefert einen vergleichbaren Score von 0 bis 100.

        Gewichtung:

        - 45 % Richtungs-Skill
        - 35 % positive Erklärungskraft
        - 20 % relativer Fehler

        Ein negatives R² reduziert den Score deutlich, weil das Modell
        schlechter als eine konstante Mittelwert-Prognose arbeitet.
        """

        direction_skill = RegressionEvaluator._clip(
            (direction_accuracy - 0.5) / 0.5
        )

        r2_skill = RegressionEvaluator._clip(r2)

        if not math.isfinite(normalized_rmse):
            rmse_skill = 0.0
        else:
            rmse_skill = RegressionEvaluator._clip(
                1.0 / (1.0 + max(0.0, normalized_rmse))
            )

        raw_score = (
            direction_skill * 0.45
            + r2_skill * 0.35
            + rmse_skill * 0.20
        )

        if r2 < 0.0:
            r2_penalty = max(
                0.10,
                1.0 / (1.0 + abs(r2) * 2.0),
            )
            raw_score *= r2_penalty

        return round(
            RegressionEvaluator._clip(raw_score) * 100.0,
            4,
        )

    @staticmethod
    def _validate_arrays(
        actual: np.ndarray,
        predicted: np.ndarray,
    ) -> None:
        if actual.size == 0:
            raise ValueError(
                "Die tatsächlichen Werte dürfen nicht leer sein."
            )

        if predicted.size == 0:
            raise ValueError(
                "Die Prognosewerte dürfen nicht leer sein."
            )

        if actual.shape != predicted.shape:
            raise ValueError(
                "Actual und predicted müssen dieselbe Länge besitzen. "
                f"Actual: {actual.shape}, predicted: {predicted.shape}"
            )

        if not np.isfinite(actual).all():
            raise ValueError(
                "Die tatsächlichen Werte enthalten NaN oder unendliche Werte."
            )

        if not np.isfinite(predicted).all():
            raise ValueError(
                "Die Prognosewerte enthalten NaN oder unendliche Werte."
            )

    @staticmethod
    def _clip(value: float) -> float:
        if not math.isfinite(value):
            return 0.0

        return max(
            0.0,
            min(1.0, float(value)),
        )
