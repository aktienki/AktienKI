import pandas as pd
import yfinance as yf

class YahooProvider:
    def __init__(self, timeout_seconds=30):
        self.timeout_seconds = timeout_seconds

    def history(self, symbol, *, interval, period, start=None):
        options = dict(
            tickers=symbol,
            interval=interval,
            auto_adjust=False,
            actions=False,
            progress=False,
            threads=False,
            timeout=self.timeout_seconds,
        )
        if start is None:
            options["period"] = period
        else:
            options["start"] = start

        frame = yf.download(**options)
        if frame is None or frame.empty:
            return pd.DataFrame()

        if isinstance(frame.columns, pd.MultiIndex):
            if symbol in frame.columns.get_level_values(-1):
                frame = frame.xs(symbol, axis=1, level=-1)
            else:
                frame.columns = frame.columns.get_level_values(0)

        return frame.sort_index()
