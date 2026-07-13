from __future__ import annotations

import numpy as np
import pandas as pd
from sklearn.metrics import (
    mean_absolute_error,
    mean_squared_error,
    r2_score,
)


class RegressionEvaluator:
    @staticmethod
    def evaluate(
        actual: pd.Series | np.ndarray,
        predicted: np.ndarray,
    ) -> dict[str, float]:
        actual_array = np.asarray(actual, dtype=float)
        predicted_array = np.asarray(predicted, dtype=float)

        return {
            "mae": float(
                mean_absolute_error(actual_array, predicted_array)
            ),
            "rmse": float(
                mean_squared_error(
                    actual_array,
                    predicted_array,
                ) ** 0.5
            ),
            "r2": float(
                r2_score(actual_array, predicted_array)
            ),
            "direction_accuracy": float(
                np.mean(
                    np.sign(actual_array)
                    == np.sign(predicted_array)
                )
            ),
        }
