from __future__ import annotations

import pandas as pd

from app.training.base import BaseTrainer
from app.training.factory import ModelFactory
from app.training.model_alias import MODEL_ALIAS

from app.training.evaluator import RegressionEvaluator


class AKIPulseTrainer(BaseTrainer):

    """
    AKI-PULSE

    1h

    3 Jahre

    24 Stunden Forecast
    """

    alias = "AKI-PULSE"

    def __init__(self):

        super().__init__()

        self.evaluator = RegressionEvaluator()

    # -----------------------------------------------------

    def train(

        self,

        train_x,

        train_y,

        valid_x,

        valid_y,

    ):

        results = []

        for algorithm in MODEL_ALIAS[self.alias]:

            adapter = ModelFactory.create(
                algorithm
            )

            model = adapter.fit(

                train_x,

                train_y,

            )

            prediction = adapter.predict(
                valid_x
            )

            score = self.evaluator.evaluate(

                y_true=valid_y,

                y_pred=prediction,

            )

            results.append(

                {

                    "algorithm": algorithm,

                    "model": model,

                    "score": score,

                }

            )

        #
        # Champion
        #

        champion = sorted(

            results,

            key=lambda x: x["score"].rmse,

        )[0]

        return champion