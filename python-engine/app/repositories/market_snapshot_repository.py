from __future__ import annotations

from datetime import datetime

import pandas as pd
from sqlalchemy import delete, insert, select

from app.database.schema import market_snapshots


class MarketSnapshotRepository:
    """
    Repository für globale Market Snapshots.
    """

    def __init__(self, session):
        self.session = session

    def insert(self, snapshot: dict) -> None:
        self.session.execute(
            insert(market_snapshots).values(
                snapshot_time=snapshot["snapshot_time"],
                market_data=snapshot["market_data"],
                feature_data=snapshot["feature_data"],
                created_at=datetime.utcnow(),
                updated_at=datetime.utcnow(),
            )
        )

    def exists(self, snapshot_time) -> bool:
        stmt = (
            select(market_snapshots.c.id)
            .where(
                market_snapshots.c.snapshot_time == snapshot_time
            )
            .limit(1)
        )

        return self.session.execute(stmt).first() is not None

    def latest(self):
        stmt = (
            select(market_snapshots)
            .order_by(
                market_snapshots.c.snapshot_time.desc()
            )
            .limit(1)
        )

        row = self.session.execute(stmt).mappings().first()

        if row is None:
            return None

        return dict(row)

    def latest_before(self, timestamp):
        stmt = (
            select(market_snapshots)
            .where(
                market_snapshots.c.snapshot_time <= timestamp
            )
            .order_by(
                market_snapshots.c.snapshot_time.desc()
            )
            .limit(1)
        )

        row = self.session.execute(stmt).mappings().first()

        if row is None:
            return None

        return dict(row)

    def between(
        self,
        start,
        end,
    ) -> pd.DataFrame:

        stmt = (
            select(market_snapshots)
            .where(
                market_snapshots.c.snapshot_time >= start,
                market_snapshots.c.snapshot_time <= end,
            )
            .order_by(
                market_snapshots.c.snapshot_time
            )
        )

        rows = self.session.execute(stmt).mappings().all()

        if not rows:
            return pd.DataFrame()

        return pd.DataFrame(rows)

    def delete_after(self, timestamp):
        self.session.execute(
            delete(market_snapshots).where(
                market_snapshots.c.snapshot_time > timestamp
            )
        )