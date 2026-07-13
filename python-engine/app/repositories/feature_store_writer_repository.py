from datetime import datetime, timezone

from sqlalchemy.dialects.postgresql import insert

from app.database.schema_feature_store import feature_store


class FeatureStoreWriterRepository:
    def __init__(self, session):
        self.session = session

    def upsert_many(
        self,
        rows: list[dict],
        *,
        batch_size: int = 1000,
    ) -> int:
        if not rows:
            return 0

        now = datetime.now(timezone.utc)
        written = 0

        for start in range(0, len(rows), batch_size):
            batch = rows[start:start + batch_size]
            values = []

            for row in batch:
                payload = dict(row)
                payload["created_at"] = now
                payload["updated_at"] = now
                values.append(payload)

            statement = insert(feature_store).values(values)

            statement = statement.on_conflict_do_update(
                index_elements=[
                    feature_store.c.instrument_id,
                    feature_store.c.interval,
                    feature_store.c.bar_time,
                ],
                set_={
                    "close": statement.excluded.close,
                    "volume": statement.excluded.volume,
                    "rsi_14": statement.excluded.rsi_14,
                    "ema_20": statement.excluded.ema_20,
                    "ema_50": statement.excluded.ema_50,
                    "ema_200": statement.excluded.ema_200,
                    "macd": statement.excluded.macd,
                    "atr_14": statement.excluded.atr_14,
                    "volatility_20": statement.excluded.volatility_20,
                    "target_return_1d": statement.excluded.target_return_1d,
                    "target_return_5d": statement.excluded.target_return_5d,
                    "target_return_20d": statement.excluded.target_return_20d,
                    "target_direction": statement.excluded.target_direction,
                    "feature_version": statement.excluded.feature_version,
                    "updated_at": now,
                },
            )

            self.session.execute(statement)
            written += len(batch)

        return written
