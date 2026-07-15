from __future__ import annotations

from app.training.consensus_engine import consensus_engine


class AKINexusTrainer:

    alias = "AKI-NEXUS"

    def train(
        self,
        short_prediction,
        long_prediction,
        market_prediction,
    ):

        return consensus_engine.calculate(

            short_prediction,

            long_prediction,

            market_prediction,

        )

    def predict(
        self,
        short_prediction,
        long_prediction,
        market_prediction,
    ):

        return self.train(

            short_prediction,

            long_prediction,

            market_prediction,

        )