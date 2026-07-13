from sqlalchemy import select

from app.database.schema import instruments
from app.models.market import Instrument


class InstrumentRepository:
    def __init__(self, session):
        self.session = session

    def active(
        self,
        *,
        types=None,
        limit=None,
        symbol=None,
        instrument_id=None,
    ):
        query = (
            select(
                instruments.c.id,
                instruments.c.symbol,
                instruments.c.provider_symbol,
                instruments.c.name,
                instruments.c.type,
                instruments.c.exchange,
                instruments.c.currency,
                instruments.c.meta,
            )
            .where(instruments.c.is_active.is_(True))
            .order_by(instruments.c.id)
        )

        if types:
            query = query.where(
                instruments.c.type.in_(list(types))
            )

        if symbol:
            query = query.where(
                (instruments.c.symbol == symbol)
                | (instruments.c.provider_symbol == symbol)
            )

        if instrument_id is not None:
            query = query.where(
                instruments.c.id == instrument_id
            )

        if limit is not None:
            query = query.limit(limit)

        rows = self.session.execute(query).mappings().all()

        return [
            Instrument(
                id=int(row["id"]),
                symbol=str(row["symbol"]),
                provider_symbol=str(
                    row["provider_symbol"] or row["symbol"]
                ),
                name=str(row["name"]),
                type=str(row["type"]),
                exchange=row.get("exchange"),
                currency=row.get("currency"),
                meta=row.get("meta") or {},
            )
            for row in rows
        ]

    def by_market_role(self, role: str):
        return [
            instrument
            for instrument in self.active()
            if instrument.market_role == role
        ]

    def benchmarks(self):
        return self.by_market_role("benchmark")

    def markets(self):
        return self.by_market_role("market")

    def volatility(self):
        return self.by_market_role("volatility")

    def interest_rates(self):
        return self.by_market_role("interest_rate")

    def currencies(self):
        return self.by_market_role("currency")

    def commodities(self):
        return self.by_market_role("commodity")

    def sectors(self):
        return self.by_market_role("sector")

    def crypto(self):
        return self.by_market_role("crypto")