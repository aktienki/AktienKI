from __future__ import annotations

from sqlalchemy import select

from app.models.market_instrument import MarketInstrument
from app.market import Instrument


class MarketRegistryRepository:
    """
    Lädt Marktinstrumente aus der bestehenden instruments-Tabelle.
    Nutzt ausschließlich das vorhandene meta-Feld.
    """

    def __init__(self, session):
        self.session = session

    def all(self) -> list[MarketInstrument]:
        stmt = (
            select(Instrument)
            .where(Instrument.is_active.is_(True))
            .order_by(Instrument.symbol)
        )

        rows = self.session.execute(stmt).scalars().all()

        return [
            MarketInstrument.from_db(row)
            for row in rows
        ]

    def enabled(self) -> list[MarketInstrument]:
        return [
            instrument
            for instrument in self.all()
            if instrument.enabled
        ]

    def by_role(self, role) -> list[MarketInstrument]:
        return [
            instrument
            for instrument in self.enabled()
            if instrument.role is role
        ]

    def benchmarks(self) -> list[MarketInstrument]:
        from app.enums.market_role import MarketRole

        return self.by_role(MarketRole.BENCHMARK)

    def currencies(self) -> list[MarketInstrument]:
        from app.enums.market_role import MarketRole

        return self.by_role(MarketRole.CURRENCY)

    def interest_rates(self) -> list[MarketInstrument]:
        from app.enums.market_role import MarketRole

        return self.by_role(MarketRole.INTEREST_RATE)

    def volatility(self) -> list[MarketInstrument]:
        from app.enums.market_role import MarketRole

        return self.by_role(MarketRole.VOLATILITY)

    def commodities(self) -> list[MarketInstrument]:
        from app.enums.market_role import MarketRole

        return self.by_role(MarketRole.COMMODITY)

    def by_symbol(
        self,
        symbol: str,
    ) -> MarketInstrument | None:
        stmt = (
            select(Instrument)
            .where(Instrument.symbol == symbol)
            .limit(1)
        )

        row = self.session.execute(stmt).scalar_one_or_none()

        if row is None:
            return None

        return MarketInstrument.from_db(row)