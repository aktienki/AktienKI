from __future__ import annotations

from app.config.model_registry import (
    MODEL_REGISTRY,
)

from app.strategies.aki_pulse import (
    AKIPulseStrategy,
)

from app.strategies.aki_horizon import (
    AKIHorizonStrategy,
)

from app.strategies.aki_climate import (
    AKIClimateStrategy,
)

from app.strategies.aki_nexus import (
    AKINexusStrategy,
)


class StrategyLoader:

    _STRATEGIES = {

        "AKI-PULSE": AKIPulseStrategy,

        "AKI-HORIZON": AKIHorizonStrategy,

        "AKI-CLIMATE": AKIClimateStrategy,

        "AKI-NEXUS": AKINexusStrategy,

    }

    @classmethod
    def load(cls, alias: str):

        if alias not in MODEL_REGISTRY:

            raise ValueError(
                f"Unknown model alias: {alias}"
            )

        if alias not in cls._STRATEGIES:

            raise ValueError(
                f"No strategy registered for: {alias}"
            )

        definition = MODEL_REGISTRY[alias]

        strategy = cls._STRATEGIES[alias]

        if alias == "AKI-NEXUS":

            return strategy()

        return strategy(definition)

    @classmethod
    def available(cls):

        return sorted(
            cls._STRATEGIES.keys()
        )

    @classmethod
    def exists(cls, alias: str):

        return alias in cls._STRATEGIES

    @classmethod
    def short_term(cls):

        return cls.load("AKI-PULSE")

    @classmethod
    def long_term(cls):

        return cls.load("AKI-HORIZON")

    @classmethod
    def market(cls):

        return cls.load("AKI-CLIMATE")

    @classmethod
    def consensus(cls):

        return cls.load("AKI-NEXUS")