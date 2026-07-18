from __future__ import annotations

from app.enums.model_scope import ModelScope
from app.providers.provider_registry import PROVIDER_REGISTRY
from app.strategies.models import StrategyProfile


class ProviderManager:
    INTERVAL_SCOPE_MAP = {
        "1d": ModelScope.LONG_TERM,
        "1h": ModelScope.SHORT_TERM,
    }

    @classmethod
    def create(cls, scope: ModelScope):
        provider_class = PROVIDER_REGISTRY.get(scope)

        if provider_class is None:
            raise RuntimeError(f"No provider for {scope}")

        return provider_class()

    @classmethod
    def from_strategy(
        cls,
        strategy_profile: StrategyProfile,
    ):
        interval = strategy_profile.interval.strip().lower()
        scope = cls.INTERVAL_SCOPE_MAP.get(interval)

        if scope is None:
            raise RuntimeError(
                "Kein Provider-Scope für Intervall "
                f"{strategy_profile.interval!r} konfiguriert."
            )

        return cls.create(scope)
