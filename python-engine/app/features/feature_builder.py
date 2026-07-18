from __future__ import annotations

import hashlib
import json

import pandas as pd

from app.features.feature_store_builder import (
    FeatureStoreBuilder,
)


class FeatureBuilder:
    """
    Abwärtskompatibler Wrapper.

    Die eigentliche Feature-Logik liegt jetzt vollständig im
    FeatureStoreBuilder.
    """

    VERSION = "2.0.0"

    TARGET_COLUMNS = [
        "target_return_1d",
        "target_return_5d",
        "target_return_20d",
        "target_return_60d",
        "target_direction",
    ]

    def __init__(self) -> None:
        self.store = FeatureStoreBuilder()

    def build(
        self,
        frame: pd.DataFrame,
        *,
        market_data: pd.DataFrame | None = None,
        cross_asset_data: pd.DataFrame | None = None,
        sector_data: pd.DataFrame | None = None,
        macro_data: pd.DataFrame | None = None,
        fundamental_data: pd.DataFrame | None = None,
    ) -> pd.DataFrame:

        if frame.empty:
            return pd.DataFrame()

        result = self.store.transform(
            frame,
            market_data=market_data,
            cross_asset_data=cross_asset_data,
            sector_data=sector_data,
            macro_data=macro_data,
            fundamental_data=fundamental_data,
        )

        result = self._create_targets(result)

        feature_columns = self.feature_columns(result)

        result["feature_version"] = self.VERSION
        result["feature_hash"] = self.feature_hash(
            feature_columns
        )

        return result

    def feature_columns(
        self,
        dataframe: pd.DataFrame,
    ) -> list[str]:

        return self.store.feature_columns(
            dataframe
        )

    @staticmethod
    def feature_hash(
        columns: list[str],
    ) -> str:

        payload = json.dumps(
            columns,
            sort_keys=False,
        ).encode()

        return hashlib.sha256(
            payload
        ).hexdigest()

    def latest_features(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:

        return self.store.latest_feature_row(
            dataframe
        )

    def training_frame(
        self,
        dataframe: pd.DataFrame,
        target: str = "target_return_5d",
    ) -> pd.DataFrame:

        return self.store.training_frame(
            dataframe,
            target_names=target,
        )

    def _create_targets(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:

        dataframe = dataframe.copy()

        dataframe["target_return_1d"] = (
            dataframe["close"].shift(-1)
            / dataframe["close"]
            - 1
        )

        dataframe["target_return_5d"] = (
            dataframe["close"].shift(-5)
            / dataframe["close"]
            - 1
        )

        dataframe["target_return_20d"] = (
            dataframe["close"].shift(-20)
            / dataframe["close"]
            - 1
        )

        dataframe["target_return_60d"] = (
            dataframe["close"].shift(-60)
            / dataframe["close"]
            - 1
        )

        dataframe["target_direction"] = 0

        dataframe.loc[
            dataframe["target_return_5d"] > 0.02,
            "target_direction",
        ] = 1

        dataframe.loc[
            dataframe["target_return_5d"] < -0.02,
            "target_direction",
        ] = -1

        return dataframe