from __future__ import annotations

import pandas as pd
from sqlalchemy import text


class FeatureStoreRepository:
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

    def load_training_data(
        self,
        *,
        instrument_id: int,
        interval: str = "1d",
        feature_version: str = "1.0.0",
        target_name: str = "target_return_5d",
    ) -> pd.DataFrame:
        if target_name not in {
            "target_return_1d",
            "target_return_5d",
            "target_return_20d",
        }:
            raise ValueError(f"Nicht unterstütztes Target: {target_name}")

        columns_sql = ", ".join(self.FEATURE_COLUMNS)

        query = text(
            f"""
            SELECT
                bar_time,
                {columns_sql},
                {target_name} AS target
            FROM feature_store
            WHERE instrument_id = :instrument_id
              AND interval = :interval
              AND feature_version = :feature_version
              AND {target_name} IS NOT NULL
            ORDER BY bar_time
            """
        )

        rows = self.session.execute(
            query,
            {
                "instrument_id": instrument_id,
                "interval": interval,
                "feature_version": feature_version,
            },
        ).mappings().all()

        if not rows:
            return pd.DataFrame()

        frame = pd.DataFrame(rows)
        frame["bar_time"] = pd.to_datetime(frame["bar_time"], utc=True)

        for column in [*self.FEATURE_COLUMNS, "target"]:
            frame[column] = pd.to_numeric(frame[column], errors="coerce")

        return frame.dropna(
            subset=[*self.FEATURE_COLUMNS, "target"]
        ).reset_index(drop=True)
