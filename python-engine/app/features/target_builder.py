from __future__ import annotations

import numpy as np
import pandas as pd


class TargetBuilder:
    """
    Erzeugt mehrere zukunftsbezogene Trainingsziele für AktienKI.

    Unterstützte Targets:
    - target_return
    - target_direction
    - target_max_gain
    - target_max_loss
    - target_volatility
    - target_risk_reward
    - target_probability
    - target_confidence
    - target_upside_capture
    - target_downside_risk

    Die bestehenden Trainingspipelines können weiterhin
    target_return als primäres Regressionsziel verwenden.
    """

    REQUIRED_COLUMNS = {
        "High",
        "Low",
        "Close",
    }

    @classmethod
    def build(
        cls,
        dataframe: pd.DataFrame,
        *,
        horizon: int,
        direction_threshold: float = 0.0,
        probability_clip: float = 0.30,
    ) -> pd.DataFrame:
        if horizon < 1:
            raise ValueError(
                "horizon muss mindestens 1 sein."
            )

        if probability_clip <= 0:
            raise ValueError(
                "probability_clip muss größer als 0 sein."
            )

        cls._validate_columns(dataframe)

        df = dataframe.copy()

        close = pd.to_numeric(
            df["Close"],
            errors="coerce",
        )

        high = pd.to_numeric(
            df["High"],
            errors="coerce",
        )

        low = pd.to_numeric(
            df["Low"],
            errors="coerce",
        )

        future_close = close.shift(
            -horizon
        )

        future_return = (
            future_close
            / close.replace(
                0,
                np.nan,
            )
            - 1
        )

        future_high = cls._forward_rolling_max(
            high,
            horizon,
        )

        future_low = cls._forward_rolling_min(
            low,
            horizon,
        )

        future_max_gain = (
            future_high
            / close.replace(
                0,
                np.nan,
            )
            - 1
        )

        future_max_loss = (
            future_low
            / close.replace(
                0,
                np.nan,
            )
            - 1
        )

        future_returns = close.pct_change()

        future_volatility = cls._forward_rolling_std(
            future_returns,
            horizon,
        )

        downside_risk = (
            future_max_loss
            .abs()
            .replace(
                0,
                np.nan,
            )
        )

        risk_reward = (
            future_max_gain
            / downside_risk
        )

        direction = pd.Series(
            0,
            index=df.index,
            dtype="int8",
        )

        direction.loc[
            future_return
            > direction_threshold
        ] = 1

        direction.loc[
            future_return
            < -direction_threshold
        ] = -1

        clipped_return = future_return.clip(
            lower=-probability_clip,
            upper=probability_clip,
        )

        probability = (
            clipped_return
            + probability_clip
        ) / (
            2
            * probability_clip
        )

        confidence = (
            1.0
            / (
                1.0
                + future_volatility.abs()
            )
        ).clip(
            lower=0.0,
            upper=1.0,
        )

        df["target_return"] = future_return
        df["target_direction"] = direction
        df["target_max_gain"] = future_max_gain
        df["target_max_loss"] = future_max_loss
        df["target_volatility"] = future_volatility
        df["target_risk_reward"] = risk_reward
        df["target_probability"] = probability
        df["target_confidence"] = confidence
        df["target_upside_capture"] = (
            future_max_gain
            .clip(
                lower=0.0
            )
        )
        df["target_downside_risk"] = (
            future_max_loss
            .clip(
                upper=0.0
            )
            .abs()
        )

        # Rückwärtskompatibilität für die bestehende Pipeline.
        df["target"] = df["target_return"]

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    @staticmethod
    def _forward_rolling_max(
        series: pd.Series,
        horizon: int,
    ) -> pd.Series:
        reversed_series = (
            series
            .shift(-1)
            .iloc[::-1]
        )

        result = (
            reversed_series
            .rolling(
                window=horizon,
                min_periods=horizon,
            )
            .max()
            .iloc[::-1]
        )

        return result

    @staticmethod
    def _forward_rolling_min(
        series: pd.Series,
        horizon: int,
    ) -> pd.Series:
        reversed_series = (
            series
            .shift(-1)
            .iloc[::-1]
        )

        result = (
            reversed_series
            .rolling(
                window=horizon,
                min_periods=horizon,
            )
            .min()
            .iloc[::-1]
        )

        return result

    @staticmethod
    def _forward_rolling_std(
        series: pd.Series,
        horizon: int,
    ) -> pd.Series:
        reversed_series = (
            series
            .shift(-1)
            .iloc[::-1]
        )

        result = (
            reversed_series
            .rolling(
                window=horizon,
                min_periods=horizon,
            )
            .std()
            .iloc[::-1]
        )

        return result

    @classmethod
    def _validate_columns(
        cls,
        dataframe: pd.DataFrame,
    ) -> None:
        missing = sorted(
            cls.REQUIRED_COLUMNS.difference(
                dataframe.columns
            )
        )

        if missing:
            raise ValueError(
                "TargetBuilder fehlen Spalten: "
                f"{missing}"
            )
