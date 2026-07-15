from __future__ import annotations

from datetime import datetime
from datetime import timedelta

import pandas as pd
import yfinance as yf


class HourlyMarketProvider:
    """
    Hourly Market Provider

    Standard:
        - 1h
        - 730 Tage (Yahoo Limit)
    """

    DEFAULT_PERIOD_DAYS = 730
    DEFAULT_INTERVAL = "1h"

    def load(
        self,
        symbol: str,
        days: int | None = None,
    ) -> pd.DataFrame:

        if days is None:
            days = self.DEFAULT_PERIOD_DAYS

        end = datetime.utcnow()
        start = end - timedelta(days=days)

        df = yf.download(
            tickers=symbol,
            start=start,
            end=end,
            interval=self.DEFAULT_INTERVAL,
            auto_adjust=True,
            progress=False,
            prepost=False,
            group_by="column",
            threads=False,
        )

        if df.empty:
            raise RuntimeError(
                f"No hourly data for {symbol}"
            )

        #
        # ----------------------------------------------------
        # Yahoo MultiIndex Fix
        # ----------------------------------------------------
        #

        if isinstance(df.columns, pd.MultiIndex):
            df.columns = df.columns.get_level_values(0)

        #
        # Standard Columns
        #

        expected = [
            "Open",
            "High",
            "Low",
            "Close",
            "Volume",
        ]

        missing = [
            c
            for c in expected
            if c not in df.columns
        ]

        if missing:
            raise RuntimeError(
                f"Missing columns: {missing}"
            )

        df = df[expected].copy()

        #
        # Datetime
        #

        df.index = pd.to_datetime(df.index)

        df.sort_index(
            inplace=True
        )

        #
        # Remove NaN
        #

        df.dropna(
            inplace=True
        )

        return df