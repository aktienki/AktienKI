from __future__ import annotations

from collections.abc import Iterable
from typing import Any

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.database.schema import instruments
from app.enums.market_role import MarketRole
from app.models.market import Instrument


class InstrumentRepository:
    def __init__(self, session: Session):
        self.session = session

    def active(
        self,
        *,
        types: Iterable[str] | None = None,
        limit: int | None = None,
        symbol: str | None = None,
        instrument_id: int | None = None,
    ) -> list[Instrument]:
        query = (
            select(
                instruments.c.id,
                instruments.c.symbol,
                instruments.c.provider_symbol,
                instruments.c.name,
                instruments.c.type,
                instruments.c.exchange_id,
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
            normalized_symbol = symbol.strip()

            query = query.where(
                (instruments.c.symbol == normalized_symbol)
                | (
                    instruments.c.provider_symbol
                    == normalized_symbol
                )
            )

        if instrument_id is not None:
            query = query.where(
                instruments.c.id == instrument_id
            )

        if limit is not None:
            if limit < 1:
                raise ValueError(
                    "limit muss größer als null sein."
                )

            query = query.limit(limit)

        rows = self.session.execute(query).mappings().all()

        return [
            self._map_instrument(row)
            for row in rows
        ]

    def by_market_role(
        self,
        role: MarketRole | str,
    ) -> list[Instrument]:
        resolved_role = self._resolve_market_role(role)

        return [
            instrument
            for instrument in self.active()
            if instrument.market_role == resolved_role.value
        ]

    def benchmarks(self) -> list[Instrument]:
        return self.by_market_role(
            MarketRole.BENCHMARK
        )

    def markets(self) -> list[Instrument]:
        return self.by_market_role(
            MarketRole.MARKET
        )

    def volatility(self) -> list[Instrument]:
        return self.by_market_role(
            MarketRole.VOLATILITY
        )

    def interest_rates(self) -> list[Instrument]:
        return self.by_market_role(
            MarketRole.INTEREST_RATE
        )

    def currencies(self) -> list[Instrument]:
        return self.by_market_role(
            MarketRole.CURRENCY
        )

    def commodities(self) -> list[Instrument]:
        return self.by_market_role(
            MarketRole.COMMODITY
        )

    def sectors(self) -> list[Instrument]:
        return self.by_market_role(
            MarketRole.SECTOR
        )

    def crypto(self) -> list[Instrument]:
        return self.by_market_role(
            MarketRole.CRYPTO
        )

    @staticmethod
    def _resolve_market_role(
        role: MarketRole | str,
    ) -> MarketRole:
        if isinstance(role, MarketRole):
            return role

        normalized_role = role.strip().lower()

        if not MarketRole.has_value(normalized_role):
            raise ValueError(
                f"Unbekannte Marktrolle: {role}"
            )

        return MarketRole(normalized_role)

    @staticmethod
    def _map_instrument(
        row: dict[str, Any],
    ) -> Instrument:
        return Instrument(
            id=int(row["id"]),
            symbol=str(row["symbol"]),
            provider_symbol=str(
                row["provider_symbol"]
                or row["symbol"]
            ),
            name=str(row["name"]),
            type=str(row["type"]),
            exchange_id=row.get("exchange_id"),
            currency=row.get("currency"),
            meta=dict(row.get("meta") or {}),
        )