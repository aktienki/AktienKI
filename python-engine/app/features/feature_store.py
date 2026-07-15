from __future__ import annotations

import numpy as np
import pandas as pd

from app.features.candlestick_features import CandlestickFeatures
from app.features.market_features import MarketFeatures
from app.features.momentum_features import MomentumFeatures
from app.features.time_features import TimeFeatures
from app.features.trend_features import TrendFeatures
from app.features.volume_features import VolumeFeatures
from app.features.volatility_features import VolatilityFeatures


class FeatureStore:
    """
    Zentraler AKI Feature Store.

    Die Pipeline erzeugt ausschlieÃŸlich Eingangsmerkmale.
    SÃ¤mtliche Target-Spalten werden zuverlÃ¤ssig aus der
    Feature-Liste ausgeschlossen.
    """

    BASE_COLUMNS = {
        "Open",
        "High",
        "Low",
        "Close",
        "Adj Close",
        "Volume",
    }

    def __init__(self) -> None:
        self.pipeline = [
            TrendFeatures,
            MomentumFeatures,
            VolumeFeatures,
            VolatilityFeatures,
            CandlestickFeatures,
            TimeFeatures,
            MarketFeatures,
        ]

    def transform(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        df = dataframe.copy()

        for feature_block in self.pipeline:
            df = feature_block.transform(df)

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    @classmethod
    def feature_columns(
        cls,
        dataframe: pd.DataFrame,
    ) -> list[str]:
        feature_names: list[str] = []

        for column in dataframe.columns:
            if column in cls.BASE_COLUMNS:
                continue

            if column == "target":
                continue

            if column.startswith("target_"):
                continue

            series = dataframe[column]

            if not pd.api.types.is_numeric_dtype(series):
                continue

            feature_names.append(column)

        cls._validate_unique(
            feature_names
        )

        return feature_names

    @staticmethod
    def _validate_unique(
        feature_names: list[str],
    ) -> None:
        duplicates = sorted(
            {
                feature
                for feature in feature_names
                if feature_names.count(feature) > 1
            }
        )

        if duplicates:
            raise ValueError(
                "Doppelte Feature-Namen erkannt: "
                f"{duplicates}"
            )
