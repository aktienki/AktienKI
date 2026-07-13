from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.metrics import (
    mean_absolute_error,
    mean_squared_error,
    r2_score,
)
from xgboost import XGBRegressor


@dataclass(frozen=True, slots=True)
class TrainingResult:
    artifact_path: str
    checksum: str
    version: str
    parameters: dict
    metrics: dict
    feature_names: list[str]
    training_rows: int
    validation_rows: int
    test_rows: int
    period_start: pd.Timestamp
    period_end: pd.Timestamp


class XGBoostTrainer:
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

    def __init__(self, storage_path: Path):
        self.storage_path = storage_path
        self.storage_path.mkdir(parents=True, exist_ok=True)

    def train(
        self,
        frame: pd.DataFrame,
        *,
        instrument_id: int,
        target_name: str,
        feature_names: list[str],
        parameters: dict | None = None,
    ) -> TrainingResult:
        if len(frame) < 300:
            raise ValueError(
                f"Zu wenig Trainingsdaten: {len(frame)} Zeilen. "
                "Mindestens 300 vollständige Zeilen werden benötigt."
            )

        parameters = {
            **self.DEFAULT_PARAMETERS,
            **(parameters or {}),
        }

        train_end = int(len(frame) * 0.80)
        validation_end = int(len(frame) * 0.90)

        train = frame.iloc[:train_end]
        validation = frame.iloc[train_end:validation_end]
        test = frame.iloc[validation_end:]

        x_train = train[feature_names]
        y_train = train["target"]

        x_validation = validation[feature_names]
        y_validation = validation["target"]

        x_test = test[feature_names]
        y_test = test["target"]

        model = XGBRegressor(**parameters)
        model.fit(
            x_train,
            y_train,
            eval_set=[(x_validation, y_validation)],
            verbose=False,
        )

        validation_prediction = model.predict(x_validation)
        test_prediction = model.predict(x_test)

        metrics = {
            "validation": self._metrics(
                y_validation,
                validation_prediction,
            ),
            "test": self._metrics(
                y_test,
                test_prediction,
            ),
            "direction_accuracy_validation": self._direction_accuracy(
                y_validation,
                validation_prediction,
            ),
            "direction_accuracy_test": self._direction_accuracy(
                y_test,
                test_prediction,
            ),
            "feature_importance": {
                name: float(value)
                for name, value in zip(
                    feature_names,
                    model.feature_importances_,
                    strict=True,
                )
            },
        }

        version = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        artifact_name = (
            f"xgboost_instrument_{instrument_id}_"
            f"{target_name}_{version}.joblib"
        )
        artifact_path = self.storage_path / artifact_name

        joblib.dump(
            {
                "model": model,
                "feature_names": feature_names,
                "target_name": target_name,
                "parameters": parameters,
                "metrics": metrics,
                "trained_at": datetime.now(timezone.utc).isoformat(),
            },
            artifact_path,
        )

        checksum = hashlib.sha256(
            artifact_path.read_bytes()
        ).hexdigest()

        metadata_path = artifact_path.with_suffix(".json")
        metadata_path.write_text(
            json.dumps(
                {
                    "version": version,
                    "instrument_id": instrument_id,
                    "target_name": target_name,
                    "feature_names": feature_names,
                    "parameters": parameters,
                    "metrics": metrics,
                    "checksum": checksum,
                },
                indent=2,
            ),
            encoding="utf-8",
        )

        return TrainingResult(
            artifact_path=str(artifact_path),
            checksum=checksum,
            version=version,
            parameters=parameters,
            metrics=metrics,
            feature_names=feature_names,
            training_rows=len(train),
            validation_rows=len(validation),
            test_rows=len(test),
            period_start=frame.iloc[0]["bar_time"],
            period_end=frame.iloc[-1]["bar_time"],
        )

    @staticmethod
    def _metrics(
        actual: pd.Series,
        predicted: np.ndarray,
    ) -> dict:
        return {
            "mae": float(mean_absolute_error(actual, predicted)),
            "rmse": float(
                mean_squared_error(
                    actual,
                    predicted,
                ) ** 0.5
            ),
            "r2": float(r2_score(actual, predicted)),
        }

    @staticmethod
    def _direction_accuracy(
        actual: pd.Series,
        predicted: np.ndarray,
    ) -> float:
        actual_direction = np.sign(actual.to_numpy())
        predicted_direction = np.sign(predicted)

        return float(
            np.mean(actual_direction == predicted_direction)
        )
