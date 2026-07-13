from __future__ import annotations

import pandas as pd
from sqlalchemy import text


class RawPriceRepository:
    def __init__(self, session):
        self.session = session

    def load(
        self,
        *,
        instrument_id: int,
        interval: str,
    ) -> pd.DataFrame:
        rows = self.session.execute(
            text(
                """
                SELECT
                    bar_time,
                    open,
                    high,
                    low,
                    close,
                    adjusted_close,
                    volume
                FROM price_bars
                WHERE instrument_id = :instrument_id
                  AND interval = :interval
                ORDER BY bar_time
                """
            ),
            {
                "instrument_id": instrument_id,
                "interval": interval,
            },
        ).mappings().all()

        if not rows:
            return pd.DataFrame()

        frame = pd.DataFrame(rows)
        frame["bar_time"] = pd.to_datetime(
            frame["bar_time"],
            utc=True,
        )
        frame = frame.set_index("bar_time")

        for column in frame.columns:
            frame[column] = pd.to_numeric(
                frame[column],
                errors="coerce",
            )

        return frame
