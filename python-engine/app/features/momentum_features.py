from __future__ import annotations

import numpy as np
import pandas as pd


class MomentumFeatures:
    RSI_PERIODS = [7, 14, 21]
    ROC_PERIODS = [3, 6, 12, 24]
    MOMENTUM_PERIODS = [3, 6, 12, 24]

    @classmethod
    def transform(cls, dataframe: pd.DataFrame) -> pd.DataFrame:
        df = dataframe.copy()

        cls._validate_columns(df)

        close = df["Close"].astype(float)
        high = df["High"].astype(float)
        low = df["Low"].astype(float)

        for period in cls.RSI_PERIODS:
            df[f"rsi_{period}"] = cls._rsi(
                close,
                period,
            )

            df[f"rsi_{period}_oversold"] = (
                df[f"rsi_{period}"] < 30
            ).astype(int)

            df[f"rsi_{period}_overbought"] = (
                df[f"rsi_{period}"] > 70
            ).astype(int)

            df[f"rsi_{period}_distance_50"] = (
                df[f"rsi_{period}"] - 50
            ) / 50

        ema_12 = close.ewm(
            span=12,
            adjust=False,
        ).mean()

        ema_26 = close.ewm(
            span=26,
            adjust=False,
        ).mean()

        df["macd"] = ema_12 - ema_26

        df["macd_signal"] = df["macd"].ewm(
            span=9,
            adjust=False,
        ).mean()

        df["macd_histogram"] = (
            df["macd"]
            - df["macd_signal"]
        )

        df["macd_above_signal"] = (
            df["macd"]
            > df["macd_signal"]
        ).astype(int)

        df["macd_histogram_positive"] = (
            df["macd_histogram"] > 0
        ).astype(int)

        df["macd_histogram_slope"] = (
            df["macd_histogram"].diff()
        )

        for period in cls.ROC_PERIODS:
            df[f"roc_{period}"] = (
                close.pct_change(period)
            )

        for period in cls.MOMENTUM_PERIODS:
            df[f"momentum_{period}"] = (
                close
                - close.shift(period)
            )

            df[f"momentum_{period}_normalized"] = (
                close
                / close.shift(period)
                - 1
            )

        df["cci_14"] = cls._cci(
            high,
            low,
            close,
            period=14,
        )

        df["cci_20"] = cls._cci(
            high,
            low,
            close,
            period=20,
        )

        df["williams_r_14"] = cls._williams_r(
            high,
            low,
            close,
            period=14,
        )

        stochastic_k, stochastic_d = cls._stochastic(
            high,
            low,
            close,
            period=14,
            smooth=3,
        )

        df["stochastic_k_14"] = stochastic_k
        df["stochastic_d_14"] = stochastic_d

        df["stochastic_cross_up"] = (
            (
                df["stochastic_k_14"]
                > df["stochastic_d_14"]
            )
            & (
                df["stochastic_k_14"].shift(1)
                <= df["stochastic_d_14"].shift(1)
            )
        ).astype(int)

        df["stochastic_cross_down"] = (
            (
                df["stochastic_k_14"]
                < df["stochastic_d_14"]
            )
            & (
                df["stochastic_k_14"].shift(1)
                >= df["stochastic_d_14"].shift(1)
            )
        ).astype(int)

        df["price_acceleration_3"] = (
            close.pct_change(1)
            - close.pct_change(1).shift(3)
        )

        df["price_acceleration_6"] = (
            close.pct_change(1)
            - close.pct_change(1).shift(6)
        )

        df["momentum_composite"] = (
            cls._zscore(df["rsi_14"])
            + cls._zscore(df["macd_histogram"])
            + cls._zscore(df["roc_12"])
            + cls._zscore(df["stochastic_k_14"])
        ) / 4

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    @staticmethod
    def _validate_columns(
        dataframe: pd.DataFrame,
    ) -> None:
        required = {
            "High",
            "Low",
            "Close",
        }

        missing = sorted(
            required.difference(
                dataframe.columns
            )
        )

        if missing:
            raise ValueError(
                "MomentumFeatures fehlen Spalten: "
                f"{missing}"
            )

    @staticmethod
    def _rsi(
        close: pd.Series,
        period: int,
    ) -> pd.Series:
        delta = close.diff()

        gain = delta.clip(
            lower=0,
        )

        loss = -delta.clip(
            upper=0,
        )

        average_gain = gain.ewm(
            alpha=1 / period,
            adjust=False,
            min_periods=period,
        ).mean()

        average_loss = loss.ewm(
            alpha=1 / period,
            adjust=False,
            min_periods=period,
        ).mean()

        relative_strength = (
            average_gain
            / average_loss.replace(0, np.nan)
        )

        rsi = (
            100
            - (
                100
                / (
                    1
                    + relative_strength
                )
            )
        )

        return rsi.fillna(50)

    @staticmethod
    def _cci(
        high: pd.Series,
        low: pd.Series,
        close: pd.Series,
        *,
        period: int,
    ) -> pd.Series:
        typical_price = (
            high
            + low
            + close
        ) / 3

        moving_average = typical_price.rolling(
            period
        ).mean()

        mean_deviation = typical_price.rolling(
            period
        ).apply(
            lambda values: np.mean(
                np.abs(
                    values
                    - np.mean(values)
                )
            ),
            raw=True,
        )

        denominator = (
            0.015
            * mean_deviation.replace(
                0,
                np.nan,
            )
        )

        return (
            typical_price
            - moving_average
        ) / denominator

    @staticmethod
    def _williams_r(
        high: pd.Series,
        low: pd.Series,
        close: pd.Series,
        *,
        period: int,
    ) -> pd.Series:
        highest_high = high.rolling(
            period
        ).max()

        lowest_low = low.rolling(
            period
        ).min()

        denominator = (
            highest_high
            - lowest_low
        ).replace(
            0,
            np.nan,
        )

        return (
            -100
            * (
                highest_high
                - close
            )
            / denominator
        )

    @staticmethod
    def _stochastic(
        high: pd.Series,
        low: pd.Series,
        close: pd.Series,
        *,
        period: int,
        smooth: int,
    ) -> tuple[pd.Series, pd.Series]:
        highest_high = high.rolling(
            period
        ).max()

        lowest_low = low.rolling(
            period
        ).min()

        denominator = (
            highest_high
            - lowest_low
        ).replace(
            0,
            np.nan,
        )

        stochastic_k = (
            100
            * (
                close
                - lowest_low
            )
            / denominator
        )

        stochastic_d = stochastic_k.rolling(
            smooth
        ).mean()

        return (
            stochastic_k,
            stochastic_d,
        )

    @staticmethod
    def _zscore(
        series: pd.Series,
        window: int = 50,
    ) -> pd.Series:
        rolling_mean = series.rolling(
            window
        ).mean()

        rolling_std = series.rolling(
            window
        ).std()

        return (
            series
            - rolling_mean
        ) / rolling_std.replace(
            0,
            np.nan,
        )
