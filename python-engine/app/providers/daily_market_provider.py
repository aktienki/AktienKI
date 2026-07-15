from __future__ import annotations

from datetime import datetime, timedelta

import pandas as pd
import yfinance as yf


class DailyMarketProvider:
    DEFAULT_PERIOD_DAYS = 3650
    DEFAULT_INTERVAL = "1d"

    def load(
        self,
        symbol: str,
        days: int | None = None,
    ) -> pd.DataFrame:
        resolved_days = (
            days
            if days is not None
            else self.DEFAULT_PERIOD_DAYS
        )

        end = datetime.utcnow()
        start = end - timedelta(days=resolved_days)

        dataframe = yf.download(
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

        if dataframe.empty:
            raise RuntimeError(
                f"Keine Tagesdaten für {symbol} verfügbar."
            )

        if isinstance(dataframe.columns, pd.MultiIndex):
            dataframe.columns = (
                dataframe.columns.get_level_values(0)
            )

        expected_columns = [
            "Open",
            "High",
            "Low",
            "Close",
            "Volume",
        ]

        missing_columns = [
            column
            for column in expected_columns
            if column not in dataframe.columns
        ]

        if missing_columns:
            raise RuntimeError(
                "Fehlende Marktdaten-Spalten: "
                f"{missing_columns}"
            )

        dataframe = dataframe[
            expected_columns
        ].copy()

        dataframe.index = pd.to_datetime(
            dataframe.index
        )
        dataframe.sort_index(inplace=True)
        dataframe.dropna(inplace=True)

        return dataframe
