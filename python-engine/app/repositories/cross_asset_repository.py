from __future__ import annotations

import pandas as pd
from sqlalchemy import text


class CrossAssetRepository:
    def __init__(self, session):
        self.session = session

    def load_frames(
        self,
        *,
        strategy_profile_id: int,
        interval: str,
    ) -> dict[str, pd.DataFrame]:
        instruments = self.session.execute(
            text(
                """
                SELECT
                    spi.instrument_id,
                    spi.alias,
                    spi.role
                FROM strategy_profile_instruments spi
                WHERE spi.strategy_profile_id = :strategy_profile_id
                  AND spi.is_enabled = true
                ORDER BY spi.id
                """
            ),
            {
                "strategy_profile_id": strategy_profile_id,
            },
        ).mappings().all()

        result: dict[str, pd.DataFrame] = {}

        for instrument in instruments:
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
                    "instrument_id": instrument["instrument_id"],
                    "interval": interval,
                },
            ).mappings().all()

            if not rows:
                continue

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

            result[str(instrument["alias"])] = frame

        return result
