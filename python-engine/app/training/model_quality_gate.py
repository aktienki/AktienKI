from __future__ import annotations

from dataclasses import dataclass


@dataclass(slots=True, frozen=True)
class QualityGateResult:
    accepted: bool
    status: str
    reasons: list[str]
    metrics: dict[str, float]


class ModelQualityGate:
    """
    Qualitätsprüfung eines trainierten Modells.
    """

    def __init__(
        self,
        *,
        min_r2: float = 0.0,
        min_direction_accuracy: float = 0.53,
        min_ensemble_score: float = 12.0,
        max_normalized_rmse: float = 1.05,
    ) -> None:

        self.min_r2 = min_r2
        self.min_direction_accuracy = min_direction_accuracy
        self.min_ensemble_score = min_ensemble_score
        self.max_normalized_rmse = max_normalized_rmse

    def evaluate(
        self,
        metrics: dict,
    ) -> QualityGateResult:

        reasons: list[str] = []

        r2 = float(metrics.get("r2", 0))
        direction_accuracy = float(
            metrics.get("direction_accuracy", 0)
        )
        ensemble_score = float(
            metrics.get("ensemble_score", 0)
        )
        normalized_rmse = float(
            metrics.get("normalized_rmse", 999)
        )

        if r2 < self.min_r2:
            reasons.append("R² zu niedrig")

        if direction_accuracy < self.min_direction_accuracy:
            reasons.append("Direction Accuracy zu niedrig")

        if ensemble_score < self.min_ensemble_score:
            reasons.append("Ensemble Score zu niedrig")

        if normalized_rmse > self.max_normalized_rmse:
            reasons.append("Normalized RMSE zu hoch")

        accepted = len(reasons) == 0

        return QualityGateResult(
            accepted=accepted,
            status="accepted" if accepted else "rejected",
            reasons=reasons,
            metrics={
                "r2": r2,
                "direction_accuracy": direction_accuracy,
                "ensemble_score": ensemble_score,
                "normalized_rmse": normalized_rmse,
            },
        )


model_quality_gate = ModelQualityGate()