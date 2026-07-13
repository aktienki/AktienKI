from __future__ import annotations

from sqlalchemy import text


class LatestFeatureRepository:
    FEATURE_COLUMNS = [
        "close",
        "volume",
        "rsi_14",
        "ema_20",
        "ema_50",
        "ema_200",
        "macd",
        "atr_14",
        "volatility_20",
    ]

    def __init__(self, session):
        self.session = session

    def latest(
        self,
        *,
        instrument_id: int,
        interval: str,
        feature_version: str,
    ) -> dict | None:
        row = self.session.execute(
            text(
                """
                SELECT
                    bar_time,
                    close,
                    volume,
                    rsi_14,
                    ema_20,
                    ema_50,
                    ema_200,
                    macd,
                    atr_14,
                    volatility_20
                FROM feature_store
                WHERE instrument_id = :instrument_id
                  AND interval = :interval
                  AND feature_version = :feature_version
                ORDER BY bar_time DESC
                LIMIT 1
                """
            ),
            {
                "instrument_id": instrument_id,
                "interval": interval,
                "feature_version": feature_version,
            },
        ).mappings().first()

        if row is None:
            return None

        return dict(row)
