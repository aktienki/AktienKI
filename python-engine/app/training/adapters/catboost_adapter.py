from __future__ import annotations

from pathlib import Path

import joblib
import numpy as np
import pandas as pd

from app.training.base import ModelAdapter, ModelTrainingResult
from app.training.evaluator import RegressionEvaluator


class CatBoostAdapter(ModelAdapter):
    name = "catboost"

    DEFAULT_PARAMETERS = {
        "iterations": 600,
        "depth": 6,
        "learning_rate": 0.03,
        "loss_function": "RMSE",
        "verbose": False,
        "random_seed": 42,
    }

    def _model_class(self):
        try:
            from catboost import CatBoostRegressor
        except ImportError as exception:
            raise RuntimeError(
                "CatBoost ist nicht installiert. "
                "Installiere es mit: python -m pip install catboost"
            ) from exception

        return CatBoostRegressor

    def train(
        self,
        *,
        x_train: pd.DataFrame,
        y_train: pd.Series,
        x_validation: pd.DataFrame,
        y_validation: pd.Series,
        parameters: dict | None = None,
    ) -> ModelTrainingResult:
        resolved = {
            **self.DEFAULT_PARAMETERS,
            **(parameters or {}),
        }

        model = self._model_class()(**resolved)
        model.fit(
            x_train,
            y_train,
            eval_set=(x_validation, y_validation),
        )

        prediction = model.predict(x_validation)
        metrics = RegressionEvaluator.evaluate(
            y_validation,
            prediction,
        )

        importance = {
            name: float(value)
            for name, value in zip(
                x_train.columns,
                model.get_feature_importance(),
                strict=True,
            )
        }

        return ModelTrainingResult(
            model=model,
            metrics=metrics,
            feature_importance=importance,
            parameters=resolved,
        )

    def predict(self, model, features: pd.DataFrame) -> np.ndarray:
        return model.predict(features)

    def save(self, model, path: Path) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump(model, path)
