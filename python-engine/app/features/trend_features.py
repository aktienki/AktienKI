from __future__ import annotations

import numpy as np
import pandas as pd


class TrendFeatures:

    EMA_PERIODS = [
        3,
        5,
        8,
        10,
        13,
        20,
        21,
        34,
        50,
        55,
        89,
        100,
        144,
        200,
    ]

    @classmethod
    def transform(cls, df: pd.DataFrame):

        df = df.copy()

        #
        # EMA
        #

        for period in cls.EMA_PERIODS:

            column = f"ema_{period}"

            df[column] = (
                df["Close"]
                .ewm(span=period)
                .mean()
            )

            df[f"{column}_distance"] = (

                df["Close"]

                / df[column]

                - 1

            )

            df[f"{column}_slope"] = (

                df[column]

                .diff()

            )

        #
        # EMA Crosses
        #

        crosses = [

            (5, 13),

            (8, 21),

            (13, 34),

            (21, 55),

            (55, 200),

        ]

        for fast, slow in crosses:

            df[f"ema_cross_{fast}_{slow}"] = (

                df[f"ema_{fast}"]

                > df[f"ema_{slow}"]

            ).astype(int)

        #
        # Trend Strength
        #

        df["trend_strength"] = (

            df["ema_20"]

            - df["ema_200"]

        ) / df["ema_200"]

        return df