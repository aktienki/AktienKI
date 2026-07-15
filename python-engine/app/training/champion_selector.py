from __future__ import annotations


class ChampionSelector:

    """
    Chooses the best model
    inside one AKI service.
    """

    @staticmethod
    def select(results):

        if len(results) == 0:

            raise RuntimeError(

                "No models available."

            )

        return sorted(

            results,

            key=lambda x: x["score"].rmse,

        )[0]