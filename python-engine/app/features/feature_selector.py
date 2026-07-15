from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import numpy as np
import pandas as pd

from app.features.feature_importance import (
    FeatureImportanceEntry,
    FeatureImportanceReport,
)


@dataclass(slots=True, frozen=True)
class FeatureSelectionResult:
    selected_features: list[str]
    removed_features: list[str]
    ranking: list[FeatureImportanceEntry]
    selected_count: int
    removed_count: int


class FeatureSelector:
    """
    Wählt anhand der Modell-Importance die wichtigsten Features aus.

    Standard:
    - maximal 50 Features
    - Mindest-Importance 0.001
    - stark korrelierte Features werden optional reduziert
    """

    def __init__(
        self,
        *,
        max_features: int = 50,
        min_importance: float = 0.001,
        correlation_threshold: float = 0.98,
    ) -> None:
        if max_features < 1:
            raise ValueError(
                "max_features muss mindestens 1 sein."
            )

        if not 0 <= min_importance <= 1:
            raise ValueError(
                "min_importance muss zwischen 0 und 1 liegen."
            )

        if not 0 < correlation_threshold <= 1:
            raise ValueError(
                "correlation_threshold muss zwischen 0 und 1 liegen."
            )

        self.max_features = max_features
        self.min_importance = min_importance
        self.correlation_threshold = correlation_threshold
        self.importance_report = FeatureImportanceReport()

    def select(
        self,
        *,
        model: Any,
        dataframe: pd.DataFrame,
        feature_names: list[str],
    ) -> FeatureSelectionResult:
        self._validate_input(
            dataframe=dataframe,
            feature_names=feature_names,
        )

        ranking = self.importance_report.extract(
            model=model,
            feature_names=feature_names,
        )

        importance_filtered = [
            entry.feature
            for entry in ranking
            if entry.importance >= self.min_importance
        ]

        if not importance_filtered:
            importance_filtered = [
                entry.feature
                for entry in ranking[: self.max_features]
            ]

        importance_filtered = (
            importance_filtered[: self.max_features]
        )

        selected_features = self._remove_correlated_features(
            dataframe=dataframe,
            features=importance_filtered,
        )

        removed_features = [
            feature
            for feature in feature_names
            if feature not in selected_features
        ]

        return FeatureSelectionResult(
            selected_features=selected_features,
            removed_features=removed_features,
            ranking=ranking,
            selected_count=len(selected_features),
            removed_count=len(removed_features),
        )

    def transform(
        self,
        *,
        dataframe: pd.DataFrame,
        selection: FeatureSelectionResult,
    ) -> pd.DataFrame:
        missing = [
            feature
            for feature in selection.selected_features
            if feature not in dataframe.columns
        ]

        if missing:
            raise ValueError(
                "Ausgewählte Features fehlen im DataFrame: "
                f"{missing}"
            )

        return dataframe[
            selection.selected_features
        ].copy()

    def _remove_correlated_features(
        self,
        *,
        dataframe: pd.DataFrame,
        features: list[str],
    ) -> list[str]:
        if len(features) <= 1:
            return features

        numeric_frame = (
            dataframe[features]
            .replace(
                [np.inf, -np.inf],
                np.nan,
            )
            .dropna()
        )

        if numeric_frame.empty:
            return features

        correlation = (
            numeric_frame
            .corr()
            .abs()
        )

        selected: list[str] = []

        for feature in features:
            if not selected:
                selected.append(feature)
                continue

            too_correlated = any(
                correlation.loc[
                    feature,
                    selected_feature,
                ]
                >= self.correlation_threshold
                for selected_feature in selected
            )

            if not too_correlated:
                selected.append(feature)

        return selected

    @staticmethod
    def _validate_input(
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

        missing = [
            feature
            for feature in feature_names
            if feature not in dataframe.columns
        ]

        if missing:
            raise ValueError(
                "Folgende Features fehlen im DataFrame: "
                f"{missing}"
            )


feature_selector = FeatureSelector()
