from __future__ import annotations


class DriftDetector:

    """
    Erkennt QualitÃ¤tsverlust.

    """

    @staticmethod
    def drift(

        current,

        previous,

    ):

        if previous is None:

            return 0.0

        return round(

            previous.score

            - current.score,

            4,

        )

    @staticmethod
    def has_drift(

        current,

        previous,

    ):

        return (

            DriftDetector.drift(

                current,

                previous,

            )

            > 5

        )