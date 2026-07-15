from __future__ import annotations

import pandas as pd


class AKIHorizonFeatures:

    """
    Long-Term Feature Engineering
    """

    def transform(self, df: pd.DataFrame):

        df = df.copy()

        for period in [

            20,

            50,

            100,

            200,

        ]:

            df[f"ema_{period}"] = (

                df.Close

                .ewm(span=period)

                .mean()

            )

        df["return_5"] = df.Close.pct_change(5)

        df["return_20"] = df.Close.pct_change(20)

        df["return_60"] = df.Close.pct_change(60)

        df["volatility"] = (

            df.Close

            .pct_change()

            .rolling(20)

            .std()

        )

        df["volume_ratio"] = (

            df.Volume

            / df.Volume.rolling(20).mean()

        )

        df["target"] = (

            df.Close.shift(-20)

            / df.Close

            - 1

        )

        return df.dropna()