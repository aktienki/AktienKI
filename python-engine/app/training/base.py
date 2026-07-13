from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import numpy as np
import pandas as pd


@dataclass(frozen=True, slots=True)
class ModelTrainingResult:
    model: Any
    metrics: dict
    feature_importance: dict[str, float]
    parameters: dict


class ModelAdapter(ABC):
    name: str

    @abstractmethod
    def train(
        self,
        *,
        x_train: pd.DataFrame,
        y_train: pd.Series,
        x_validation: pd.DataFrame,
        y_validation: pd.Series,
        parameters: dict | None = None,
    ) -> ModelTrainingResult:
        raise NotImplementedError

    @abstractmethod
    def predict(
        self,
        model: Any,
        features: pd.DataFrame,
    ) -> np.ndarray:
        raise NotImplementedError

    @abstractmethod
    def save(self, model: Any, path: Path) -> None:
        raise NotImplementedError
