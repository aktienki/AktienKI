from __future__ import annotations

from dataclasses import dataclass
from typing import Any


@dataclass(slots=True, frozen=True)
class QualityGateResult:
    accepted: bool
    status: str
    reasons: list[str]
    metrics: dict[str, float]


class ModelQualityGate:
    """
    Prüft, ob ein trainiertes Modell als AKI-PRIME zugelassen werden darf.

    Standardregeln:
    - R² darf nicht deutlich negativ sein
    - Richtungsgenauigkeit muss über der Zufallsbasis liegen
    - Ensemble-Score muss einen Mindestwert erreichen
    - normalisierter RMSE darf nicht zu hoch sein
    """

    def __init__(
        self,
        *,
        min_r2: float = 0.0,
        min_direction_accuracy: float = 0.53,
        min_ensemble_score: float = 12.0,
        max_normalized_rmse: float = 1.05,
    ) -> None:
        self.min_r2 = float(min_r2)
        self.min_direction_accuracy = float(
            min_direction_accuracy
        )
        self.min_ensemble_score = float(
            min_ensemble_score
        )
        self.max_normalized_rmse = float(
            max_normalized_rmse
        )

    def evaluate(
        self,
        metrics: dict[str, Any],
    ) -> QualityGateResult:
        required = {
            "r2",
            "direction_accuracy",
            "ensemble_score",
            "normalized_rmse",
        }

        missing = sorted(
            required.difference(
                metrics.keys()
            )
        )

        if missing:
            return QualityGateResult(
                accepted=False,
                status="invalid_metrics",
                reasons=[
                    "Fehlende Metriken: "
                    + ", ".join(missing)
                ],
                metrics=self._numeric_metrics(
                    metrics
                ),
            )

        numeric = self._numeric_metrics(
            metrics
        )

        reasons: list[str] = []

        if numeric["r2"] < self.min_r2:
            reasons.append(
                "R² unter Mindestwert "
                f"({numeric['r2']:.4f} < "
                f"{self.min_r2:.4f})"
            )

        if (
            numeric["direction_accuracy"]
            < self.min_direction_accuracy
        ):
            reasons.append(
                "Richtungsgenauigkeit unter Mindestwert "
                f"({numeric['direction_accuracy']:.4f} < "
                f"{self.min_direction_accuracy:.4f})"
            )

        if (
            numeric["ensemble_score"]
            < self.min_ensemble_score
        ):
            reasons.append(
                "Ensemble-Score unter Mindestwert "
                f"({numeric['ensemble_score']:.4f} < "
                f"{self.min_ensemble_score:.4f})"
            )

        if (
            numeric["normalized_rmse"]
            > self.max_normalized_rmse
        ):
            reasons.append(
                "Normalisierter RMSE über Grenzwert "
                f"({numeric['normalized_rmse']:.4f} > "
                f"{self.max_normalized_rmse:.4f})"
            )

        accepted = not reasons

        return QualityGateResult(
            accepted=accepted,
            status=(
                "accepted"
                if accepted
                else "rejected"
            ),
            reasons=reasons,
            metrics=numeric,
        )

    def evaluate_training_result(
        self,
        result: Any,
    ) -> QualityGateResult:
        if hasattr(
            result,
            "winner_metrics",
        ):
            return self.evaluate(
                result.winner_metrics
            )

        if isinstance(
            result,
            dict,
        ) and "winner_metrics" in result:
            return self.evaluate(
                result["winner_metrics"]
            )

        raise TypeError(
            "Das Trainingsergebnis enthält keine "
            "winner_metrics."
        )

    @staticmethod
    def _numeric_metrics(
        metrics: dict[str, Any],
    ) -> dict[str, float]:
        numeric: dict[str, float] = {}

        for key, value in metrics.items():
            try:
                numeric[key] = float(value)
            except (
                TypeError,
                ValueError,
            ):
                continue

        return numeric


model_quality_gate = ModelQualityGate()
