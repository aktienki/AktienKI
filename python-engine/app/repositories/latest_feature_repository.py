from __future__ import annotations

from sqlalchemy import text


class LatestFeatureRepository:
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

        feature = dict(row)

        snapshot = self._latest_market_snapshot(
            feature["bar_time"],
        )

        if snapshot is not None:

            feature.update(
                snapshot.get(
                    "feature_data",
                    {},
                )
            )

        for column in self.MARKET_FEATURE_COLUMNS:
            feature.setdefault(column, 0.0)

        return feature

    def _latest_market_snapshot(
        self,
        bar_time,
    ) -> dict | None:

        row = self.session.execute(
            text(
                """
                SELECT
                    snapshot_time,
                    market_data,
                    feature_data
                FROM market_snapshots
                WHERE snapshot_time <= :bar_time
                ORDER BY snapshot_time DESC
                LIMIT 1
                """
            ),
            {
                "bar_time": bar_time,
            },
        ).mappings().first()

        if row is None:
            return None

        return dict(row)