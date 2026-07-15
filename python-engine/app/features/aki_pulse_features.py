from __future__ import annotations

import numpy as np
import pandas as pd


class AKIPulseFeatures:

    """
    Short-Term Feature Engineering

    1h
    """

    def transform(self, df: pd.DataFrame):

        df = df.copy()

        #
        # Returns
        #

        df["return_1"] = df.Close.pct_change(1)
        df["return_2"] = df.Close.pct_change(2)
        df["return_4"] = df.Close.pct_change(4)
        df["return_8"] = df.Close.pct_change(8)
        df["return_24"] = df.Close.pct_change(24)

        #
        # EMA
        #

        for period in [5, 10, 20, 50]:

            df[f"ema_{period}"] = (

                df.Close

                .ewm(span=period)

                .mean()

            )

        #
        # Distance
        #

        df["ema5_distance"] = (

            df.Close

            / df.ema_5

            - 1

        )

        df["ema20_distance"] = (

            df.Close

            / df.ema_20

            - 1

        )

        #
        # Volatility
        #

        df["volatility_24"] = (

            df.Close

            .pct_change()

            .rolling(24)

            .std()

        )

        #
        # Candle
        #

        df["body"] = df.Close - df.Open

        df["range"] = df.High - df.Low

        df["upper_shadow"] = df.High - df[["Close", "Open"]].max(axis=1)

        df["lower_shadow"] = df[["Close", "Open"]].min(axis=1) - df.Low

        #
        # Volume
        #

        df["volume_mean"] = (

            df.Volume

            .rolling(24)

            .mean()

        )

        df["volume_ratio"] = (

            df.Volume

            / df.volume_mean

        )

        #
        # Time
        #

        df["hour"] = df.index.hour

        df["weekday"] = df.index.dayofweek

        #
        # Target
        #

        df["target"] = (

            df.Close

            .shift(-24)

            / df.Close

            - 1

        )

        return df.dropna()