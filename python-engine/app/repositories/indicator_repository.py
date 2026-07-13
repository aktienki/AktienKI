from datetime import datetime, timezone

from sqlalchemy.dialects.postgresql import insert

from app.database.schema_indicators import technical_indicators


class IndicatorRepository:
    def __init__(self, session):
        self.session = session

    def upsert_many(self, rows: list[dict], *, batch_size: int = 1000) -> int:
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

            statement = insert(technical_indicators).values(values)

            update_columns = {
                column.name: getattr(statement.excluded, column.name)
                for column in technical_indicators.columns
                if column.name not in {
                    "id",
                    "instrument_id",
                    "interval",
                    "bar_time",
                    "created_at",
                }
            }

            statement = statement.on_conflict_do_update(
                index_elements=[
                    technical_indicators.c.instrument_id,
                    technical_indicators.c.interval,
                    technical_indicators.c.bar_time,
                ],
                set_=update_columns,
            )

            self.session.execute(statement)
            written += len(batch)

        return written
