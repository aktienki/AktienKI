from __future__ import annotations

import pandas as pd

from app.strategies.base_strategy import BaseStrategy


class AKIHorizonStrategy(BaseStrategy):
    """
    Long-Term AI

    Timeframe:
        1d

    Training:
        10 Jahre

    Prediction:
        20 Handelstage
    """

    def create_features(self, dataframe: pd.DataFrame):

        df = dataframe.copy()

        #
        # Trend
        #

        df["ema_20"] = df["Close"].ewm(span=20).mean()
        df["ema_50"] = df["Close"].ewm(span=50).mean()
        df["ema_100"] = df["Close"].ewm(span=100).mean()
        df["ema_200"] = df["Close"].ewm(span=200).mean()

        #
        # Returns
        #

        df["return_5"] = df["Close"].pct_change(5)
        df["return_10"] = df["Close"].pct_change(10)
        df["return_20"] = df["Close"].pct_change(20)
        df["return_60"] = df["Close"].pct_change(60)
        df["return_120"] = df["Close"].pct_change(120)

        #
        # Volatility
        #

        df["volatility_20"] = (
            df["Close"]
            .pct_change()
            .rolling(20)
            .std()
        )

        df["volatility_60"] = (
            df["Close"]
            .pct_change()
            .rolling(60)
            .std()
        )

        #
        # Volume
        #

        df["volume_mean_20"] = (
            df["Volume"]
            .rolling(20)
            .mean()
        )

        df["volume_ratio"] = (
            df["Volume"]
            / df["volume_mean_20"]
        )

        #
        # High / Low
        #

        df["rolling_high_50"] = (
            df["High"]
            .rolling(50)
            .max()
        )

        df["rolling_low_50"] = (
            df["Low"]
            .rolling(50)
            .min()
        )

        #
        # Time
        #

        df["month"] = df.index.month

        df["quarter"] = df.index.quarter

        return df

    # ---------------------------------------------------------

    def create_target(self, dataframe):

        df = dataframe.copy()

        horizon = 20

        df["target"] = (
            df["Close"]
            .shift(-horizon)
            / df["Close"]
            - 1
        )

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