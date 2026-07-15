from __future__ import annotations

import numpy as np
import pandas as pd


class CandlestickFeatures:
    """
    Candlestick Pattern Features
    """

    @classmethod
    def transform(cls, dataframe: pd.DataFrame):

        df = dataframe.copy()

        open_ = df["Open"].astype(float)
        high = df["High"].astype(float)
        low = df["Low"].astype(float)
        close = df["Close"].astype(float)

        body = close - open_

        body_size = body.abs()

        candle_range = (
            high - low
        ).replace(0, np.nan)

        upper_shadow = (
            high
            - np.maximum(open_, close)
        )

        lower_shadow = (
            np.minimum(open_, close)
            - low
        )

        #
        # Basic
        #

        df["body"] = body

        df["body_size"] = body_size

        df["upper_shadow"] = upper_shadow

        df["lower_shadow"] = lower_shadow

        df["body_percent"] = (

            body_size

            / candle_range

        )

        #
        # Doji
        #

        df["doji"] = (

            df["body_percent"] < 0.10

        ).astype(int)

        #
        # Hammer
        #

        df["hammer"] = (

            (lower_shadow > body_size * 2)

            &

            (upper_shadow < body_size)

        ).astype(int)

        #
        # Shooting Star
        #

        df["shooting_star"] = (

            (upper_shadow > body_size * 2)

            &

            (lower_shadow < body_size)

        ).astype(int)

        #
        # Bull Candle
        #

        df["bull_candle"] = (

            close > open_

        ).astype(int)

        #
        # Bear Candle
        #

        df["bear_candle"] = (

            close < open_

        ).astype(int)

        #
        # Engulfing Bull
        #

        previous_open = open_.shift(1)

        previous_close = close.shift(1)

        df["bullish_engulfing"] = (

            (previous_close < previous_open)

            &

            (close > open_)

            &

            (close > previous_open)

            &

            (open_ < previous_close)

        ).astype(int)

        #
        # Engulfing Bear
        #

        df["bearish_engulfing"] = (

            (previous_close > previous_open)

            &

            (close < open_)

            &

            (close < previous_open)

            &

            (open_ > previous_close)

        ).astype(int)

        #
        # Inside Bar
        #

        df["inside_bar"] = (

            (high < high.shift(1))

            &

            (low > low.shift(1))

        ).astype(int)

        #
        # Outside Bar
        #

        df["outside_bar"] = (

            (high > high.shift(1))

            &

            (low < low.shift(1))

        ).astype(int)

        #
        # Gap
        #

        df["gap_up"] = (

            open_

            > high.shift(1)

        ).astype(int)

        df["gap_down"] = (

            open_

            < low.shift(1)

        ).astype(int)

        #
        # Marubozu
        #

        df["marubozu"] = (

            (upper_shadow < candle_range * 0.05)

            &

            (lower_shadow < candle_range * 0.05)

        ).astype(int)

        #
        # Long Candle
        #

        df["long_candle"] = (

            body_size

            >

            body_size.rolling(20).mean()

            * 1.5

        ).astype(int)

        return df.replace(

            [np.inf, -np.inf],

            np.nan,

        )