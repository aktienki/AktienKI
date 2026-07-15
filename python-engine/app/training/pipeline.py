from __future__ import annotations

from app.training.dataset_builder import (
    DatasetBuilder,
)

from app.training.train_test_split import (
    TrainTestSplit,
)

from app.trainers.aki_pulse_trainer import (
    AKIPulseTrainer,
)

from app.trainers.aki_horizon_trainer import (
    AKIHorizonTrainer,
)

from app.enums.model_scope import (
    ModelScope,
)


class TrainingPipeline:

    def __init__(self):

        self.builder = DatasetBuilder()

    # ----------------------------------------------------

    def train(

        self,

        symbol,

        strategy,

    ):

        dataframe, target = self.builder.build(

            symbol,

            strategy,

        )

        (

            train_x,

            train_y,

            valid_x,

            valid_y,

        ) = TrainTestSplit.split(

            dataframe,

            target,

        )

        if strategy.scope == ModelScope.SHORT_TERM:

            trainer = AKIPulseTrainer()

        else:

            trainer = AKIHorizonTrainer()

        return trainer.train(

            train_x,

            train_y,

            valid_x,

            valid_y,

        )