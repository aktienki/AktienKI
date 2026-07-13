from __future__ import annotations

from enum import StrEnum


class MarketRole(StrEnum):
    """
    Rollen von Marktinstrumenten innerhalb der Market Intelligence Engine.
    """

    BENCHMARK = "benchmark"
    MARKET = "market"
    VOLATILITY = "volatility"
    INTEREST_RATE = "interest_rate"
    CURRENCY = "currency"
    COMMODITY = "commodity"
    SECTOR = "sector"
    CRYPTO = "crypto"
    MACRO = "macro"
    CUSTOM = "custom"

    @classmethod
    def from_string(cls, value: str) -> "MarketRole":
        value = value.strip().lower()

        for role in cls:
            if role.value == value:
                return role

        raise ValueError(f"Unknown market role: {value}")

    @classmethod
    def values(cls) -> list[str]:
        return [role.value for role in cls]

    @classmethod
    def has_value(cls, value: str) -> bool:
        return value.lower() in cls.values()

    def __str__(self) -> str:
        return self.value


    @classmethod
    def from_string(cls, value: str) -> "MarketRole":
        value = value.strip().lower()

        for role in cls:
            if role.value == value:
                return role

        raise ValueError(f"Unknown market role: {value}")    