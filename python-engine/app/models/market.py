from dataclasses import dataclass, field
from datetime import datetime
from decimal import Decimal
from typing import Any


@dataclass(frozen=True, slots=True)
class Instrument:
    """
    Repräsentiert einen Datensatz aus der instruments-Tabelle.
    Neue Felder sind optional, damit bestehender Code unverändert weiterläuft.
    """

    id: int
    symbol: str
    provider_symbol: str
    name: str
    type: str

    exchange_id: int | None = None
    currency: str | None = None
    country: str | None = None
    sector: str | None = None
    industry: str | None = None
    market_cap: Decimal | None = None

    meta: dict[str, Any] = field(default_factory=dict)

    @property
    def market_role(self) -> str:
        return self.meta.get("market_role", "custom")

    @property
    def priority(self) -> int:
        return int(self.meta.get("priority", 100))

    @property
    def provider(self) -> str:
        return self.meta.get("provider", "yfinance")

    @property
    def enabled(self) -> bool:
        return bool(self.meta.get("enabled", True))

    @property
    def feature_enabled(self) -> bool:
        return bool(self.meta.get("feature_enabled", True))

    def has_role(self, role: str) -> bool:
        return self.market_role == role

@dataclass(frozen=True, slots=True)
class PriceBar:
    instrument_id: int
    interval: str
    bar_time: datetime
    open: Decimal
    high: Decimal
    low: Decimal
    close: Decimal
    adjusted_close: Decimal | None
    volume: Decimal | None
    source: str = "yahoo"