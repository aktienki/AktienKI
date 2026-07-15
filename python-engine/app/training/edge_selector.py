from __future__ import annotations


class EdgeSelector:
    """
    AKI-EDGE

    Zweitbestes Modell.

    Wird später automatisch gegen
    AKI-PRIME getestet.
    """

    @staticmethod
    def select(candidates):

        if len(candidates) < 2:

            return None

        candidates.sort(

            key=lambda x: x.metrics["ensemble_score"],

            reverse=True,

        )

        return candidates[1]