from __future__ import annotations

from dataclasses import dataclass

from app.training.model_quality_gate import (
    model_quality_gate,
)


@dataclass(slots=True)
class PrimeModel:

    alias: str

    algorithm: str

    metrics: dict

    version: str

    feature_count: int

    artifact_path: str


class PrimeSelector:
    """
    AKI-PRIME

    Nur Modelle, welche das Quality Gate bestehen,
    dürfen Champion werden.
    """

    def select(
        self,
        candidates,
    ):

        accepted = []

        rejected = []

        for candidate in candidates:

            gate = model_quality_gate.evaluate(

                candidate.metrics

            )

            if gate.accepted:

                accepted.append(candidate)

            else:

                rejected.append(

                    {

                        "algorithm": candidate.algorithm,

                        "reason": gate.reasons,

                    }

                )

        if not accepted:

            raise RuntimeError(

                "Kein Modell hat das Quality Gate bestanden."

            )

        accepted.sort(

            key=lambda x: x.metrics["ensemble_score"],

            reverse=True,

        )

        winner = accepted[0]

        return PrimeModel(

            alias="AKI-PRIME",

            algorithm=winner.algorithm,

            metrics=winner.metrics,

            version=winner.training_result.version,

            feature_count=len(

                winner.training_result.feature_names

            ),

            artifact_path=winner.training_result.artifact_path,

        )