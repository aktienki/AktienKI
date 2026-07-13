from datetime import datetime, timezone
from sqlalchemy import select, func
from sqlalchemy.dialects.postgresql import insert
from app.database.schema import price_bars

class PriceBarRepository:
    def __init__(self, session):
        self.session = session

    def latest_time(self, instrument_id, interval):
        return self.session.scalar(
            select(func.max(price_bars.c.bar_time)).where(
                price_bars.c.instrument_id == instrument_id,
                price_bars.c.interval == interval,
            )
        )

    def upsert_many(self, bars, *, batch_size):
        rows = list(bars)
        if not rows:
            return 0
        now = datetime.now(timezone.utc)
        total = 0

        for start in range(0, len(rows), batch_size):
            batch = rows[start:start + batch_size]
            values = [{
                "instrument_id": b.instrument_id,
                "interval": b.interval,
                "bar_time": b.bar_time,
                "open": b.open,
                "high": b.high,
                "low": b.low,
                "close": b.close,
                "adjusted_close": b.adjusted_close,
                "volume": b.volume,
                "source": b.source,
                "created_at": now,
                "updated_at": now,
            } for b in batch]

            stmt = insert(price_bars).values(values)
            stmt = stmt.on_conflict_do_update(
                index_elements=[price_bars.c.instrument_id, price_bars.c.interval, price_bars.c.bar_time],
                set_={
                    "open": stmt.excluded.open,
                    "high": stmt.excluded.high,
                    "low": stmt.excluded.low,
                    "close": stmt.excluded.close,
                    "adjusted_close": stmt.excluded.adjusted_close,
                    "volume": stmt.excluded.volume,
                    "source": stmt.excluded.source,
                    "updated_at": now,
                },
            )
            self.session.execute(stmt)
            total += len(batch)

        return total
