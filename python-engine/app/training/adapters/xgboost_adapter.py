from __future__ import annotations

from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from xgboost import XGBRegressor

from app.training.base import ModelAdapter, ModelTrainingResult
from app.training.evaluator import RegressionEvaluator


class XGBoostAdapter(ModelAdapter):
    name = "xgboost"

    DEFAULT_PARAMETERS = {
        "n_estimators": 600,
        "max_depth": 5,
        "learning_rate": 0.03,
        "subsample": 0.85,
        "colsample_bytree": 0.85,
        "reg_alpha": 0.05,
        "reg_lambda": 1.0,
        "objective": "reg:squarederror",
        "random_state": 42,
        "n_jobs": -1,
    }

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

        model = XGBRegressor(**resolved)
        model.fit(
            x_train,
            y_train,
            eval_set=[(x_validation, y_validation)],
            verbose=False,
        )

        validation_prediction = model.predict(x_validation)

        metrics = RegressionEvaluator.evaluate(
            y_validation,
            validation_prediction,
        )

        importance = {
            name: float(value)
            for name, value in zip(
                x_train.columns,
                model.feature_importances_,
                strict=True,
            )
        }

        return ModelTrainingResult(
            model=model,
            metrics=metrics,
            feature_importance=importance,
            parameters=resolved,
        )

    def predict(
        self,
        model: XGBRegressor,
        features: pd.DataFrame,
    ) -> np.ndarray:
        return model.predict(features)

    def save(self, model: XGBRegressor, path: Path) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump(model, path)
