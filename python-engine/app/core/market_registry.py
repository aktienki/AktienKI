from __future__ import annotations

from collections import Counter

from app.enums.market_role import MarketRole
from app.repositories.instrument_repository import InstrumentRepository


class MarketRegistry:
    """
    Zentrale Registry für Markt- und Cross-Asset-Instrumente.

    Die Registry verwendet ausschließlich die bestehende
    InstrumentRepository-Implementierung und hält die geladenen
    Instrumente während eines Laufes im Speicher.
    """

    def __init__(self, session):
        self._repository = InstrumentRepository(session)
        self._cache = None

    def reload(self) -> "MarketRegistry":
        self._cache = self._repository.active()
        return self

    @property
    def instruments(self):
        if self._cache is None:
            self.reload()

        return self._cache

    def all(self):
        return list(self.instruments)

    def enabled(self):
        return [
            instrument
            for instrument in self.instruments
            if instrument.enabled
        ]

    def by_role(
        self,
        role: MarketRole | str,
    ):
        resolved_role = (
            role
            if isinstance(role, MarketRole)
            else MarketRole.from_string(role)
        )

        return [
            instrument
            for instrument in self.enabled()
            if instrument.market_role == resolved_role.value
        ]

    def benchmarks(self):
        return self.by_role(MarketRole.BENCHMARK)

    def markets(self):
        return self.by_role(MarketRole.MARKET)

    def volatility(self):
        return self.by_role(MarketRole.VOLATILITY)

    def interest_rates(self):
        return self.by_role(MarketRole.INTEREST_RATE)

    def currencies(self):
        return self.by_role(MarketRole.CURRENCY)

    def commodities(self):
        return self.by_role(MarketRole.COMMODITY)

    def sectors(self):
        return self.by_role(MarketRole.SECTOR)

    def crypto(self):
        return self.by_role(MarketRole.CRYPTO)

    def macro(self):
        return self.by_role(MarketRole.MACRO)

    def custom(self):
        return self.by_role(MarketRole.CUSTOM)

    def summary(self) -> dict:
        enabled = self.enabled()

        roles = Counter(
            instrument.market_role
            for instrument in enabled
        )

        return {
            "total": len(enabled),
            "roles": dict(sorted(roles.items())),
        }

    def symbols(self) -> list[str]:
        return [
            instrument.symbol
            for instrument in self.enabled()
        ]

    def provider_symbols(self) -> list[str]:
        return [
            instrument.provider_symbol
            for instrument in self.enabled()
        ]
