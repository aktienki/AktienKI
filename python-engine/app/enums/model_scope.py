from enum import Enum


class ModelScope(str, Enum):
    """
    Logical AI domains inside AKI-Core.
    """

    SHORT_TERM = "short_term"

    LONG_TERM = "long_term"

    MARKET = "market"

    CONSENSUS = "consensus"

    PORTFOLIO = "portfolio"

    FOREX = "forex"

    CRYPTO = "crypto"