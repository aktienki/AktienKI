from __future__ import annotations

from app.training.edge_selector import EdgeSelector
from app.training.prime_selector import PrimeSelector


class ChallengerEngine:

    """
    AKI Champion / Challenger
    """

    def evaluate(

        self,

        candidates,

    ):

        prime = PrimeSelector().select(

            candidates

        )

        edge = EdgeSelector.select(

            candidates

        )

        return {

            "prime": prime,

            "edge": edge,

        }