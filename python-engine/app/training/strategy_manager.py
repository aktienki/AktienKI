from __future__ import annotations

from app.training.strategy_loader import StrategyLoader
from app.config.model_registry import MODEL_REGISTRY


class StrategyManager:

    def __init__(self):

        self._cache = {}

    # ---------------------------------------------------------

    def get(self, alias: str):

        if alias not in self._cache:

            self._cache[alias] = StrategyLoader.load(alias)

        return self._cache[alias]

    # ---------------------------------------------------------

    def pulse(self):

        return self.get("AKI-PULSE")

    # ---------------------------------------------------------

    def horizon(self):

        return self.get("AKI-HORIZON")

    # ---------------------------------------------------------

    def climate(self):

        return self.get("AKI-CLIMATE")

    # ---------------------------------------------------------

    def nexus(self):

        return self.get("AKI-NEXUS")

    # ---------------------------------------------------------

    def available(self):

        return list(MODEL_REGISTRY.keys())

    # ---------------------------------------------------------

    def exists(self, alias: str):

        return alias in MODEL_REGISTRY

    # ---------------------------------------------------------

    def definition(self, alias: str):

        return MODEL_REGISTRY[alias]

    # ---------------------------------------------------------

    def timeframe(self, alias: str):

        return MODEL_REGISTRY[alias].timeframe

    # ---------------------------------------------------------

    def scope(self, alias: str):

        return MODEL_REGISTRY[alias].scope

    # ---------------------------------------------------------

    def training_window(self, alias: str):

        return MODEL_REGISTRY[alias].training_window_days

    # ---------------------------------------------------------

    def prediction_horizon(self, alias: str):

        return MODEL_REGISTRY[
            alias
        ].prediction_horizon_minutes


strategy_manager = StrategyManager()