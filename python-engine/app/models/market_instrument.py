from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from app.enums.market_role import MarketRole


@dataclass(slots=True)
class MarketInstrument:
    """
    Repräsentiert ein Instrument aus der Tabelle instruments.
    """

    instrument_id: int

    symbol: str

    provider_symbol: str

    name: str

    instrument_type: str

    exchange: str | None = None

    currency: str | None = None

    provider: str = "yfinance"

    enabled: bool = True

    priority: int = 100

    role: MarketRole = MarketRole.CUSTOM

    meta: dict[str, Any] = field(default_factory=dict)

    @classmethod
    def from_db(cls, instrument) -> "MarketInstrument":
        """
        Erstellt ein MarketInstrument aus deinem SQLAlchemy-Model.
        """

        meta = instrument.meta or {}

        return cls(
            instrument_id=instrument.id,
            symbol=instrument.symbol,
            provider_symbol=instrument.provider_symbol
            or instrument.symbol,
            name=instrument.name,
            instrument_type=instrument.type,
            exchange=getattr(instrument, "exchange", None),
            currency=getattr(instrument, "currency", None),
            provider=meta.get("provider", "yfinance"),
            enabled=meta.get("enabled", True),
            priority=meta.get("priority", 100),
            role=MarketRole.from_string(
                meta.get("market_role", "custom")
            ),
            meta=meta,
        )

    @property
    def is_market(self) -> bool:
        return self.role is MarketRole.MARKET

    @property
    def is_benchmark(self) -> bool:
        return self.role is MarketRole.BENCHMARK

    @property
    def is_currency(self) -> bool:
        return self.role is MarketRole.CURRENCY

    @property
    def is_interest_rate(self) -> bool:
        return self.role is MarketRole.INTEREST_RATE

    @property
    def is_volatility(self) -> bool:
        return self.role is MarketRole.VOLATILITY

    @property
    def is_commodity(self) -> bool:
        return self.role is MarketRole.COMMODITY

    @property
    def is_crypto(self) -> bool:
        return self.role is MarketRole.CRYPTO

    def has_role(self, role: MarketRole) -> bool:
        return self.role is role

    def to_dict(self) -> dict[str, Any]:
        return {
            "instrument_id": self.instrument_id,
            "symbol": self.symbol,
            "provider_symbol": self.provider_symbol,
            "name": self.name,
            "instrument_type": self.instrument_type,
            "exchange": self.exchange,
            "currency": self.currency,
            "provider": self.provider,
            "enabled": self.enabled,
            "priority": self.priority,
            "role": self.role.value,
            "meta": self.meta,
        }