import pandas as pd
from sqlalchemy import select

from app.database.schema import price_bars


class PriceHistoryRepository:
    def __init__(self, session):
        self.session = session

    def load(self, instrument_id: int, interval: str) -> pd.DataFrame:
        query = (
            select(
                price_bars.c.bar_time,
                price_bars.c.open,
                price_bars.c.high,
                price_bars.c.low,
                price_bars.c.close,
                price_bars.c.adjusted_close,
                price_bars.c.volume,
            )
            .where(
                price_bars.c.instrument_id == instrument_id,
                price_bars.c.interval == interval,
            )
            .order_by(price_bars.c.bar_time)
        )

        rows = self.session.execute(query).mappings().all()

        if not rows:
            return pd.DataFrame()

        frame = pd.DataFrame(rows)
        frame["bar_time"] = pd.to_datetime(frame["bar_time"], utc=True)
        frame = frame.set_index("bar_time")

        numeric_columns = [
            "open",
            "high",
            "low",
            "close",
            "adjusted_close",
            "volume",
        ]

        for column in numeric_columns:
            frame[column] = pd.to_numeric(frame[column], errors="coerce")

        return frame
