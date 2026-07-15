from __future__ import annotations

from dataclasses import asdict

from app.strategies.aki_nexus import AKINexusStrategy


class ConsensusEngine:

    """
    Multi Horizon Consensus

    Short-Term

            +

    Long-Term

            +

    Market

            =

    Final Decision
    """

    def __init__(self):

        self.strategy = AKINexusStrategy()

    # ---------------------------------------------------------

    def calculate(

        self,

        short_prediction,

        long_prediction,

        market_prediction,

    ):

        result = self.strategy.calculate(

            short_prediction,

            long_prediction,

            market_prediction,

        )

        return asdict(result)

    # ---------------------------------------------------------

    def direction(

        self,

        short_prediction,

        long_prediction,

        market_prediction,

    ):

        return self.calculate(

            short_prediction,

            long_prediction,

            market_prediction,

        )["direction"]

    # ---------------------------------------------------------

    def score(

        self,

        short_prediction,

        long_prediction,

        market_prediction,

    ):

        return self.calculate(

            short_prediction,

            long_prediction,

            market_prediction,

        )["score"]

    # ---------------------------------------------------------

    def confidence(

        self,

        short_prediction,

        long_prediction,

        market_prediction,

    ):

        return self.calculate(

            short_prediction,

            long_prediction,

            market_prediction,

        )["confidence"]

    # ---------------------------------------------------------

    def status(

        self,

        short_prediction,

        long_prediction,

        market_prediction,

    ):

        return self.calculate(

            short_prediction,

            long_prediction,

            market_prediction,

        )["status"]

    # ---------------------------------------------------------

    def interpretation(

        self,

        short_prediction,

        long_prediction,

        market_prediction,

    ):

        return self.calculate(

            short_prediction,

            long_prediction,

            market_prediction,

        )["interpretation"]


consensus_engine = ConsensusEngine()