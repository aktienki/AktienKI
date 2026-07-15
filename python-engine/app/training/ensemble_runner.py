from __future__ import annotations

from app.training.pipeline import TrainingPipeline

from app.training.strategy_manager import (
    strategy_manager,
)


class EnsembleRunner:

    """
    Runs every AKI model.

    AKI-PULSE

    AKI-HORIZON

    AKI-CLIMATE
    """

    def __init__(self):

        self.pipeline = TrainingPipeline()

    # -----------------------------------------------------

    def run(

        self,

        symbol: str,

    ):

        results = {}

        for alias in [

            "AKI-PULSE",

            "AKI-HORIZON",

            "AKI-CLIMATE",

        ]:

            strategy = strategy_manager.get(alias)

            results[alias] = self.pipeline.train(

                symbol,

                strategy,

            )

        return results