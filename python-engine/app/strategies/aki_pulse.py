from __future__ import annotations

import pandas as pd

from app.strategies.base_strategy import BaseStrategy


class AKIPulseStrategy(BaseStrategy):
    """
    Short-Term AI

    Timeframe:
        1h

    Training:
        3 Jahre

    Prediction:
        24 Stunden
    """

    def create_features(self, dataframe: pd.DataFrame):

        df = dataframe.copy()

        #
        # Moving Averages
        #

        df["ema_5"] = df["Close"].ewm(span=5).mean()
        df["ema_10"] = df["Close"].ewm(span=10).mean()
        df["ema_20"] = df["Close"].ewm(span=20).mean()

        #
        # Returns
        #

        df["return_1"] = df["Close"].pct_change(1)
        df["return_3"] = df["Close"].pct_change(3)
        df["return_6"] = df["Close"].pct_change(6)
        df["return_12"] = df["Close"].pct_change(12)
        df["return_24"] = df["Close"].pct_change(24)

        #
        # Volume
        #

        df["volume_mean_24"] = (
            df["Volume"]
            .rolling(24)
            .mean()
        )

        df["volume_ratio"] = (
            df["Volume"]
            / df["volume_mean_24"]
        )

        #
        # Volatility
        #

        df["volatility_24"] = (
            df["Close"]
            .pct_change()
            .rolling(24)
            .std()
        )

        #
        # Candle
        #

        df["body"] = (
            df["Close"]
            - df["Open"]
        )

        df["range"] = (
            df["High"]
            - df["Low"]
        )

        #
        # Time Features
        #

        df["hour"] = df.index.hour

        df["dayofweek"] = df.index.dayofweek

        return df

    # ------------------------------------------------------

    def create_target(self, dataframe):

        df = dataframe.copy()

        horizon = 24

        df["target"] = (
            df["Close"]
            .shift(-horizon)
            / df["Close"]
            - 1
        )

        return df

    # ------------------------------------------------------

    def train(self, dataframe):

        raise NotImplementedError

    # ------------------------------------------------------

    def predict(self, dataframe):

        raise NotImplementedError

    # ------------------------------------------------------

    def evaluate(self, prediction, truth):

        raise NotImplementedError