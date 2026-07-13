from __future__ import annotations

import pandas as pd
from sqlalchemy import text


class FeatureStoreRepository:
    """
    Lädt Trainingsdaten aus dem Feature Store.

    Aktuell werden ausschließlich Instrumenten-Features verwendet.
    Die Market-Features werden in Sprint 17.1 über den
    MarketSnapshotBuilder ergänzt.
    """

    BASE_FEATURE_COLUMNS = [
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

    MARKET_FEATURE_COLUMNS = [
        "market_bull_score",
        "market_bear_score",
        "market_volatility_score",
        "market_liquidity_score",
        "market_risk_score",
        "market_momentum_score",
    ]

    FEATURE_COLUMNS = [
        *BASE_FEATURE_COLUMNS,
        *MARKET_FEATURE_COLUMNS,
    ]

    SUPPORTED_TARGETS = {
        "target_return_1d",
        "target_return_5d",
        "target_return_20d",
    }

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

        if target_name not in self.SUPPORTED_TARGETS:
            raise ValueError(
                f"Nicht unterstütztes Target: {target_name}"
            )

        columns_sql = ", ".join(self.BASE_FEATURE_COLUMNS)

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

        frame["bar_time"] = pd.to_datetime(
            frame["bar_time"],
            utc=True,
        )

        for column in [
            *self.BASE_FEATURE_COLUMNS,
            "target",
        ]:
            frame[column] = pd.to_numeric(
                frame[column],
                errors="coerce",
            )

        frame = frame.dropna(
            subset=[
                *self.BASE_FEATURE_COLUMNS,
                "target",
            ]
        ).reset_index(drop=True)

        #
        # Market Snapshot Features werden in Sprint 17.1
        # ergänzt.
        #
        for column in self.MARKET_FEATURE_COLUMNS:
            frame[column] = 0.0

        return frame