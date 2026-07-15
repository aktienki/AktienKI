from __future__ import annotations

import pandas as pd


class TimeFeatures:

    @classmethod
    def transform(cls, dataframe: pd.DataFrame):

        df = dataframe.copy()

        index = pd.to_datetime(df.index)

        df["hour"] = index.hour

        df["weekday"] = index.dayofweek

        df["month"] = index.month

        df["quarter"] = index.quarter

        df["day"] = index.day

        df["is_monday"] = (

            index.dayofweek == 0

        ).astype(int)

        df["is_friday"] = (

            index.dayofweek == 4

        ).astype(int)

        df["is_month_start"] = (

            index.is_month_start

        ).astype(int)

        df["is_month_end"] = (

            index.is_month_end

        ).astype(int)

        df["is_quarter_start"] = (

            index.is_quarter_start

        ).astype(int)

        df["is_quarter_end"] = (

            index.is_quarter_end

        ).astype(int)

        #
        # US Session
        #

        df["us_open"] = (

            (index.hour >= 15)

            &

            (index.hour <= 17)

        ).astype(int)

        df["us_close"] = (

            (index.hour >= 20)

            &

            (index.hour <= 22)

        ).astype(int)

        return df