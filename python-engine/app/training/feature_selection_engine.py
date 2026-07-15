from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import pandas as pd

from app.features.feature_selector import (
    FeatureSelectionResult,
    FeatureSelector,
)
from app.training.ensemble_trainer import (
    EnsembleTrainer,
    EnsembleTrainingResult,
)
from app.training.model_quality_gate import (
    ModelQualityGate,
    QualityGateResult,
)


@dataclass(slots=True)
class FeatureSelectionTrainingResult:
    selected_result: EnsembleTrainingResult
    selected_x_train: pd.DataFrame
    selected_x_validation: pd.DataFrame
    selection: FeatureSelectionResult | None
    mode: str
    baseline_result: EnsembleTrainingResult
    reduced_result: EnsembleTrainingResult | None
    baseline_quality: QualityGateResult
    reduced_quality: QualityGateResult | None


class FeatureSelectionEngine:
    """
    Trainiert zunächst ein Baseline-Ensemble mit allen Features.

    Danach:
    - Feature Importance bestimmen
    - Features reduzieren
    - reduziertes Ensemble trainieren
    - automatisch die bessere Variante auswählen

    Dadurch kann die Feature-Selektion das produktive Modell
    nicht verschlechtern.
    """

    def __init__(
        self,
        *,
        max_features: int = 50,
        min_importance: float = 0.001,
        correlation_threshold: float = 0.98,
        quality_gate: ModelQualityGate | None = None,
    ) -> None:
        self.selector = FeatureSelector(
            max_features=max_features,
            min_importance=min_importance,
            correlation_threshold=correlation_threshold,
        )

        self.quality_gate = (
            quality_gate
            if quality_gate is not None
            else ModelQualityGate()
        )

    def train(
        self,
        *,
        x_train: pd.DataFrame,
        y_train: pd.Series,
        x_validation: pd.DataFrame,
        y_validation: pd.Series,
    ) -> FeatureSelectionTrainingResult:
        baseline_result = EnsembleTrainer().train(
            x_train=x_train,
            y_train=y_train,
            x_validation=x_validation,
            y_validation=y_validation,
        )

        baseline_quality = self.quality_gate.evaluate(
            baseline_result.winner_metrics
        )

        baseline_model = self._model_from_result(
            baseline_result
        )

        selection = self.selector.select(
            model=baseline_model,
            dataframe=x_train,
            feature_names=list(x_train.columns),
        )

        selected_x_train = self.selector.transform(
            dataframe=x_train,
            selection=selection,
        )

        selected_x_validation = self.selector.transform(
            dataframe=x_validation,
            selection=selection,
        )

        reduced_result = EnsembleTrainer().train(
            x_train=selected_x_train,
            y_train=y_train,
            x_validation=selected_x_validation,
            y_validation=y_validation,
        )

        reduced_quality = self.quality_gate.evaluate(
            reduced_result.winner_metrics
        )

        use_reduced = self._should_use_reduced(
            baseline_result=baseline_result,
            reduced_result=reduced_result,
            baseline_quality=baseline_quality,
            reduced_quality=reduced_quality,
        )

        if use_reduced:
            return FeatureSelectionTrainingResult(
                selected_result=reduced_result,
                selected_x_train=selected_x_train,
                selected_x_validation=selected_x_validation,
                selection=selection,
                mode="reduced",
                baseline_result=baseline_result,
                reduced_result=reduced_result,
                baseline_quality=baseline_quality,
                reduced_quality=reduced_quality,
            )

        return FeatureSelectionTrainingResult(
            selected_result=baseline_result,
            selected_x_train=x_train,
            selected_x_validation=x_validation,
            selection=None,
            mode="baseline",
            baseline_result=baseline_result,
            reduced_result=reduced_result,
            baseline_quality=baseline_quality,
            reduced_quality=reduced_quality,
        )

    @staticmethod
    def _should_use_reduced(
        *,
        baseline_result: EnsembleTrainingResult,
        reduced_result: EnsembleTrainingResult,
        baseline_quality: QualityGateResult,
        reduced_quality: QualityGateResult,
    ) -> bool:
        baseline_score = float(
            baseline_result.winner_metrics.get(
                "ensemble_score",
                0.0,
            )
        )

        reduced_score = float(
            reduced_result.winner_metrics.get(
                "ensemble_score",
                0.0,
            )
        )

        baseline_r2 = float(
            baseline_result.winner_metrics.get(
                "r2",
                float("-inf"),
            )
        )

        reduced_r2 = float(
            reduced_result.winner_metrics.get(
                "r2",
                float("-inf"),
            )
        )

        if reduced_quality.accepted and not baseline_quality.accepted:
            return True

        if baseline_quality.accepted and not reduced_quality.accepted:
            return False

        if reduced_score > baseline_score and reduced_r2 >= baseline_r2:
            return True

        return False

    @staticmethod
    def _model_from_result(
        result: EnsembleTrainingResult,
    ) -> Any:
        training_result = result.winner_training_result

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


feature_selection_engine = FeatureSelectionEngine()
