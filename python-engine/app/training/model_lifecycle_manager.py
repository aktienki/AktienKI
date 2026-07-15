from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Any

from app.training.drift_detector import DriftDetector
from app.training.model_history import (
    ModelHistory,
    ModelHistoryEntry,
)
from app.training.model_quality_gate import (
    ModelQualityGate,
)
from app.training.retraining_engine import RetrainingEngine


@dataclass(slots=True, frozen=True)
class LifecycleDecision:
    alias: str
    promote: bool
    retrain: bool
    drift_detected: bool
    status: str
    reasons: list[str]
    current_version: str | None
    previous_version: str | None


class ModelLifecycleManager:
    """
    Verwaltet den Lebenszyklus eines AKI-Modells.

    Aufgaben:
    - Trainingslauf historisieren
    - Quality Gate prüfen
    - Drift erkennen
    - Retraining entscheiden
    - Promotion zu AKI-PRIME vorbereiten
    """

    def __init__(
        self,
        *,
        history: ModelHistory | None = None,
        quality_gate: ModelQualityGate | None = None,
    ) -> None:
        self.history = history or ModelHistory()
        self.quality_gate = quality_gate or ModelQualityGate()

    def register_training(
        self,
        *,
        alias: str,
        algorithm: str,
        version: str,
        metrics: dict[str, Any],
        feature_count: int,
        champion: bool = False,
        trained_at: datetime | None = None,
    ) -> ModelHistoryEntry:
        timestamp = trained_at or datetime.now(timezone.utc)

        entry = ModelHistoryEntry(
            alias=alias,
            version=version,
            algorithm=algorithm,
            score=float(
                metrics.get(
                    "ensemble_score",
                    0.0,
                )
            ),
            direction_accuracy=float(
                metrics.get(
                    "direction_accuracy",
                    0.0,
                )
            ),
            rmse=float(
                metrics.get(
                    "rmse",
                    0.0,
                )
            ),
            r2=float(
                metrics.get(
                    "r2",
                    0.0,
                )
            ),
            feature_count=int(feature_count),
            trained_at=timestamp,
            champion=bool(champion),
        )

        self.history.add(entry)

        return entry

    def evaluate_candidate(
        self,
        *,
        alias: str,
        algorithm: str,
        version: str,
        metrics: dict[str, Any],
        feature_count: int,
        trained_at: datetime | None = None,
    ) -> LifecycleDecision:
        previous = self.history.latest(alias)

        gate = self.quality_gate.evaluate(metrics)

        current = self.register_training(
            alias=alias,
            algorithm=algorithm,
            version=version,
            metrics=metrics,
            feature_count=feature_count,
            champion=False,
            trained_at=trained_at,
        )

        drift_detected = DriftDetector.has_drift(
            current,
            previous,
        )

        retrain = RetrainingEngine.should_retrain(
            previous,
        )

        reasons = list(gate.reasons)

        if drift_detected:
            reasons.append(
                "Qualitätsdrift gegenüber dem vorherigen "
                "Trainingslauf erkannt."
            )

        if retrain:
            reasons.append(
                "Retraining ist aufgrund des Modellalters "
                "oder fehlender Historie erforderlich."
            )

        promote = gate.accepted and not drift_detected

        if promote:
            current.champion = True
            status = "promote_to_prime"
            reasons.append(
                "Quality Gate bestanden und kein kritischer "
                "Drift erkannt."
            )
        elif not gate.accepted:
            status = "rejected"
        elif drift_detected:
            status = "hold_for_review"
        else:
            status = "accepted"

        return LifecycleDecision(
            alias=alias,
            promote=promote,
            retrain=retrain,
            drift_detected=drift_detected,
            status=status,
            reasons=reasons,
            current_version=current.version,
            previous_version=(
                previous.version
                if previous is not None
                else None
            ),
        )

    def latest(
        self,
        alias: str,
    ) -> ModelHistoryEntry | None:
        return self.history.latest(alias)

    def champion(
        self,
        alias: str,
    ) -> ModelHistoryEntry | None:
        return self.history.champion(alias)


model_lifecycle_manager = ModelLifecycleManager()
