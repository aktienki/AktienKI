from __future__ import annotations

import pandas as pd

from app.strategies.base_strategy import BaseStrategy


class AKIClimateStrategy(BaseStrategy):
    """
    Market Intelligence

    Liefert kein klassisches Kursziel,
    sondern bewertet das Marktumfeld.
    """

    def create_features(self, dataframe: pd.DataFrame):

        df = dataframe.copy()

        #
        # Trend
        #

        df["ema_20"] = df["Close"].ewm(span=20).mean()
        df["ema_50"] = df["Close"].ewm(span=50).mean()

        #
        # Momentum
        #

        df["return_5"] = df["Close"].pct_change(5)
        df["return_20"] = df["Close"].pct_change(20)

        #
        # Volatilität
        #

        df["volatility_20"] = (
            df["Close"]
            .pct_change()
            .rolling(20)
            .std()
        )

        #
        # Marktbreite
        #

        if "AdvanceDecline" in df.columns:
            df["market_breadth"] = df["AdvanceDecline"]

        #
        # VIX
        #

        if "VIX" in df.columns:
            df["vix"] = df["VIX"]

        #
        # Dollar
        #

        if "DXY" in df.columns:
            df["dxy"] = df["DXY"]

        #
        # Zinsen
        #

        if "US10Y" in df.columns:
            df["us10y"] = df["US10Y"]

        #
        # Gold
        #

        if "GOLD" in df.columns:
            df["gold"] = df["GOLD"]

        #
        # Öl
        #

        if "OIL" in df.columns:
            df["oil"] = df["OIL"]

        return df

    # ---------------------------------------------------------

    def create_target(self, dataframe):

        df = dataframe.copy()

        #
        # Marktregime
        #
        #  1 = Bull
        #  0 = Neutral
        # -1 = Bear
        #

        df["target"] = 0

        bull = (
            df["Close"] > df["ema_50"]
        )

        bear = (
            df["Close"] < df["ema_50"]
        )

        df.loc[bull, "target"] = 1

        df.loc[bear, "target"] = -1

        return df

    # ---------------------------------------------------------

    def train(self, dataframe):

        raise NotImplementedError

    # ---------------------------------------------------------

    def predict(self, dataframe):

        raise NotImplementedError

    # ---------------------------------------------------------

    def evaluate(self, prediction, truth):

        raise NotImplementedError