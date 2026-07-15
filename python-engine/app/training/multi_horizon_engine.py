from __future__ import annotations

from app.training.training_runner import TrainingRunner
from app.training.strategy_manager import strategy_manager


class MultiHorizonEngine:

    """
    AKI Multi Horizon Engine

    AKI-PULSE
            │
            ▼
    AKI-HORIZON
            │
            ▼
    AKI-CLIMATE
            │
            ▼
    AKI-NEXUS
    """

    def __init__(self):

        self.runner = TrainingRunner()

        self.nexus = strategy_manager.nexus()

    # ------------------------------------------------------

    def run(self):

        pulse = self.runner.pulse()

        horizon = self.runner.horizon()

        climate = self.runner.climate()

        consensus = self.nexus.calculate(

            pulse["evaluation"],

            horizon["evaluation"],

            climate["evaluation"],

        )

        return {

            "pulse": pulse,

            "horizon": horizon,

            "climate": climate,

            "consensus": consensus,

        }

    # ------------------------------------------------------

    def short_term_only(self):

        return self.runner.pulse()

    # ------------------------------------------------------

    def long_term_only(self):

        return self.runner.horizon()

    # ------------------------------------------------------

    def market_only(self):

        return self.runner.climate()

    # ------------------------------------------------------

    def consensus_only(self):

        result = self.run()

        return result["consensus"]