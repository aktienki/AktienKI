from __future__ import annotations

from app.training.strategy_manager import strategy_manager
from app.training.training_context import TrainingContext


class TrainingRunner:

    """
    Entry Point für alle AKI Modelle.

    Beispiele:

        runner.run("AKI-PULSE")

        runner.run("AKI-HORIZON")

        runner.run("AKI-CLIMATE")
    """

    def run(self, alias: str):

        #
        # Strategy
        #

        strategy = strategy_manager.get(alias)

        #
        # Context
        #

        context = TrainingContext.from_strategy(
            strategy
        )

        print(
            f"Starting {context.alias}"
        )

        #
        # Load data
        #

        from app.training.dataset_builder import DatasetBuilder

        builder = DatasetBuilder()

        dataframe, target = builder.build(

            symbol="NVDA",

            strategy=strategy,

        )

        #
        # Features
        #

        dataframe = strategy.create_features(
            dataframe
        )

        #
        # Target
        #

        dataframe = strategy.create_target(
            dataframe
        )

        #
        # Train
        #

        model = strategy.train(
            dataframe
        )

        #
        # Evaluate
        #

        evaluation = strategy.evaluate(
            model,
            dataframe,
        )

        return {

            "alias": context.alias,

            "scope": context.scope,

            "timeframe": context.timeframe,

            "training_window_days":
                context.training_window_days,

            "prediction_horizon_minutes":
                context.prediction_horizon_minutes,

            "evaluation": evaluation,

            "model": model,

        }

    # ----------------------------------------------------

    def pulse(self):

        return self.run("AKI-PULSE")

    # ----------------------------------------------------

    def horizon(self):

        return self.run("AKI-HORIZON")

    # ----------------------------------------------------

    def climate(self):

        return self.run("AKI-CLIMATE")