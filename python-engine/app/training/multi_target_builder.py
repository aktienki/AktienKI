from __future__ import annotations

from dataclasses import dataclass

import pandas as pd


@dataclass(slots=True)
class MultiTargetDataset:

    features: pd.DataFrame

    return_target: pd.Series

    direction_target: pd.Series

    probability_target: pd.Series

    confidence_target: pd.Series

    max_gain_target: pd.Series

    max_loss_target: pd.Series

    risk_reward_target: pd.Series

    volatility_target: pd.Series


class MultiTargetBuilder:

    @staticmethod
    def build(

        dataframe: pd.DataFrame,

    ) -> MultiTargetDataset:

        feature_columns = [

            c

            for c in dataframe.columns

            if not c.startswith("target")

        ]

        return MultiTargetDataset(

            features=dataframe[feature_columns],

            return_target=dataframe["target_return"],

            direction_target=dataframe["target_direction"],

            probability_target=dataframe["target_probability"],

            confidence_target=dataframe["target_confidence"],

            max_gain_target=dataframe["target_max_gain"],

            max_loss_target=dataframe["target_max_loss"],

            risk_reward_target=dataframe["target_risk_reward"],

            volatility_target=dataframe["target_volatility"],

        )