from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone
from enum import StrEnum
from typing import Any


class RetrainingReason(StrEnum):
    SCHEDULED = "scheduled"
    MODEL_TOO_OLD = "model_too_old"
    PERFORMANCE_DROP = "performance_drop"
    DATA_DRIFT = "data_drift"
    MANUAL = "manual"
    FAILED_MODEL = "failed_model"


@dataclass(frozen=True, slots=True)
class RetrainingPolicy:
    enabled: bool = True
    interval_days: int = 7
    maximum_model_age_days: int = 30
    minimum_selection_score: float | None = None
    minimum_performance_drop: float = 0.10
    minimum_drift_score: float = 0.20
    retry_failed_models: bool = True

    def __post_init__(self) -> None:
        if self.interval_days < 1:
            raise ValueError("interval_days muss mindestens 1 sein")

        if self.maximum_model_age_days < 1:
            raise ValueError(
                "maximum_model_age_days muss mindestens 1 sein"
            )

        if not 0.0 <= self.minimum_performance_drop <= 1.0:
            raise ValueError(
                "minimum_performance_drop muss zwischen 0 und 1 liegen"
            )

        if not 0.0 <= self.minimum_drift_score <= 1.0:
            raise ValueError(
                "minimum_drift_score muss zwischen 0 und 1 liegen"
            )


@dataclass(frozen=True, slots=True)
class RetrainingCandidate:
    profile_name: str
    profile_version: str
    last_trained_at: datetime | None = None
    current_selection_score: float | None = None
    baseline_selection_score: float | None = None
    drift_score: float | None = None
    model_failed: bool = False
    manual_request: bool = False
    metadata: dict[str, Any] = field(default_factory=dict)

    def __post_init__(self) -> None:
        if not self.profile_name.strip():
            raise ValueError("profile_name darf nicht leer sein")

        if not self.profile_version.strip():
            raise ValueError("profile_version darf nicht leer sein")

        if self.last_trained_at is not None:
            if self.last_trained_at.tzinfo is None:
                raise ValueError(
                    "last_trained_at muss eine Zeitzone enthalten"
                )

        if self.drift_score is not None:
            if not 0.0 <= self.drift_score <= 1.0:
                raise ValueError(
                    "drift_score muss zwischen 0 und 1 liegen"
                )


@dataclass(frozen=True, slots=True)
class RetrainingDecision:
    should_retrain: bool
    priority: int
    reasons: tuple[RetrainingReason, ...]
    due_at: datetime | None
    model_age_days: float | None
    performance_drop: float | None
    metadata: dict[str, Any] = field(default_factory=dict)


class RetrainingScheduler:
    def evaluate(
        self,
        *,
        candidate: RetrainingCandidate,
        policy: RetrainingPolicy,
        now: datetime | None = None,
    ) -> RetrainingDecision:
        current_time = now or datetime.now(timezone.utc)

        if current_time.tzinfo is None:
            raise ValueError("now muss eine Zeitzone enthalten")

        if not policy.enabled:
            return RetrainingDecision(
                should_retrain=False,
                priority=0,
                reasons=(),
                due_at=None,
                model_age_days=self._model_age_days(
                    candidate.last_trained_at,
                    current_time,
                ),
                performance_drop=self._performance_drop(candidate),
            )

        reasons: list[RetrainingReason] = []
        priority = 0

        model_age_days = self._model_age_days(
            candidate.last_trained_at,
            current_time,
        )
        performance_drop = self._performance_drop(candidate)

        due_at = (
            None
            if candidate.last_trained_at is None
            else candidate.last_trained_at
            + timedelta(days=policy.interval_days)
        )

        if candidate.manual_request:
            reasons.append(RetrainingReason.MANUAL)
            priority = max(priority, 100)

        if candidate.model_failed and policy.retry_failed_models:
            reasons.append(RetrainingReason.FAILED_MODEL)
            priority = max(priority, 90)

        if candidate.last_trained_at is None:
            reasons.append(RetrainingReason.SCHEDULED)
            priority = max(priority, 80)

        elif due_at is not None and current_time >= due_at:
            reasons.append(RetrainingReason.SCHEDULED)
            priority = max(priority, 50)

        if (
            model_age_days is not None
            and model_age_days >= policy.maximum_model_age_days
        ):
            reasons.append(RetrainingReason.MODEL_TOO_OLD)
            priority = max(priority, 70)

        if (
            performance_drop is not None
            and performance_drop >= policy.minimum_performance_drop
        ):
            reasons.append(RetrainingReason.PERFORMANCE_DROP)
            priority = max(priority, 75)

        if (
            policy.minimum_selection_score is not None
            and candidate.current_selection_score is not None
            and candidate.current_selection_score
            < policy.minimum_selection_score
        ):
            if RetrainingReason.PERFORMANCE_DROP not in reasons:
                reasons.append(RetrainingReason.PERFORMANCE_DROP)
            priority = max(priority, 75)

        if (
            candidate.drift_score is not None
            and candidate.drift_score >= policy.minimum_drift_score
        ):
            reasons.append(RetrainingReason.DATA_DRIFT)
            priority = max(priority, 85)

        return RetrainingDecision(
            should_retrain=bool(reasons),
            priority=priority,
            reasons=tuple(reasons),
            due_at=due_at,
            model_age_days=model_age_days,
            performance_drop=performance_drop,
            metadata={
                "profile_name": candidate.profile_name,
                "profile_version": candidate.profile_version,
                **candidate.metadata,
            },
        )

    def prioritize(
        self,
        *,
        candidates: list[RetrainingCandidate],
        policies: dict[str, RetrainingPolicy],
        default_policy: RetrainingPolicy | None = None,
        now: datetime | None = None,
    ) -> list[tuple[RetrainingCandidate, RetrainingDecision]]:
        fallback_policy = default_policy or RetrainingPolicy()

        decisions = [
            (
                candidate,
                self.evaluate(
                    candidate=candidate,
                    policy=policies.get(
                        candidate.profile_name,
                        fallback_policy,
                    ),
                    now=now,
                ),
            )
            for candidate in candidates
        ]

        return sorted(
            (
                item
                for item in decisions
                if item[1].should_retrain
            ),
            key=lambda item: (
                item[1].priority,
                item[1].model_age_days or 0.0,
            ),
            reverse=True,
        )

    @staticmethod
    def _model_age_days(
        last_trained_at: datetime | None,
        now: datetime,
    ) -> float | None:
        if last_trained_at is None:
            return None

        return max(
            0.0,
            (now - last_trained_at).total_seconds() / 86_400,
        )

    @staticmethod
    def _performance_drop(
        candidate: RetrainingCandidate,
    ) -> float | None:
        baseline = candidate.baseline_selection_score
        current = candidate.current_selection_score

        if baseline is None or current is None or baseline <= 0:
            return None

        return max(0.0, (baseline - current) / baseline)