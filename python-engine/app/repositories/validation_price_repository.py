from __future__ import annotations

from datetime import datetime, timedelta

from sqlalchemy import text


class ValidationPriceRepository:
    def __init__(self, session):
        self.session = session

    def price_window(
        self,
        *,
        instrument_id: int,
        prediction_time: datetime,
        horizon_days: int,
    ) -> list[dict]:
        rows = self.session.execute(
            text(
                """
                SELECT
                    bar_time,
                    open,
                    high,
                    low,
                    close
                FROM price_bars
                WHERE instrument_id = :instrument_id
                  AND interval = '1d'
                  AND bar_time > :prediction_time
                ORDER BY bar_time
                LIMIT :horizon_days
                """
            ),
            {
                "instrument_id": instrument_id,
                "prediction_time": prediction_time,
                "horizon_days": horizon_days,
            },
        ).mappings().all()

        return [dict(row) for row in rows]
