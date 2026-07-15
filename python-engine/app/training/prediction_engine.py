from __future__ import annotations

from app.training.multi_horizon_engine import (
    MultiHorizonEngine,
)


class PredictionEngine:

    """
    Central Prediction Engine

        AKI-PULSE
        AKI-HORIZON
        AKI-CLIMATE

                ↓

        AKI-NEXUS

                ↓

        Final Recommendation
    """

    def __init__(self):

        self.engine = MultiHorizonEngine()

    # -----------------------------------------------------

    def predict(self):

        result = self.engine.run()

        consensus = result["consensus"]

        return {

            #
            # Individual Models
            #

            "pulse": result["pulse"],

            "horizon": result["horizon"],

            "climate": result["climate"],

            #
            # Consensus
            #

            "direction": consensus.direction,

            "score": consensus.score,

            "confidence": consensus.confidence,

            "status": consensus.status,

            "interpretation": consensus.interpretation,

        }

    # -----------------------------------------------------

    def short_term(self):

        return self.engine.short_term_only()

    # -----------------------------------------------------

    def long_term(self):

        return self.engine.long_term_only()

    # -----------------------------------------------------

    def market(self):

        return self.engine.market_only()

    # -----------------------------------------------------

    def consensus(self):

        return self.engine.consensus_only()