from __future__ import annotations

from datetime import datetime
from datetime import timedelta


class RetrainingEngine:

    """
    Entscheidet,

    ob ein Modell neu trainiert werden soll.
    """

    DEFAULT_INTERVAL = 7

    @classmethod
    def should_retrain(

        cls,

        latest_training,

    ):

        if latest_training is None:

            return True

        age = (

            datetime.utcnow()

            - latest_training.trained_at

        )

        return (

            age

            >= timedelta(

                days=cls.DEFAULT_INTERVAL

            )

        )