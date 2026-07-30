from __future__ import annotations

from datetime import datetime, timedelta, timezone

from app.training.experiments.retraining import (
    RetrainingCandidate,
    RetrainingPolicy,
    RetrainingReason,
    RetrainingScheduler,
)


NOW = datetime(2026, 7, 19, 12, 0, tzinfo=timezone.utc)


def test_new_model_is_scheduled() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="default",
        profile_version="1.0",
        last_trained_at=None,
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(),
        now=NOW,
    )

    assert decision.should_retrain is True
    assert RetrainingReason.SCHEDULED in decision.reasons
    assert decision.priority == 80


def test_weekly_retraining_is_due() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="weekly",
        profile_version="1.0",
        last_trained_at=NOW - timedelta(days=8),
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(interval_days=7),
        now=NOW,
    )

    assert decision.should_retrain is True
    assert RetrainingReason.SCHEDULED in decision.reasons
    assert decision.model_age_days == 8.0


def test_fresh_model_is_not_due() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="fresh",
        profile_version="1.0",
        last_trained_at=NOW - timedelta(days=2),
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(interval_days=7),
        now=NOW,
    )

    assert decision.should_retrain is False
    assert decision.reasons == ()
    assert decision.priority == 0


def test_old_model_gets_higher_priority() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="old",
        profile_version="1.0",
        last_trained_at=NOW - timedelta(days=40),
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(
            interval_days=60,
            maximum_model_age_days=30,
        ),
        now=NOW,
    )

    assert decision.should_retrain is True
    assert RetrainingReason.MODEL_TOO_OLD in decision.reasons
    assert decision.priority == 70


def test_performance_drop_triggers_retraining() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="performance",
        profile_version="1.0",
        last_trained_at=NOW - timedelta(days=1),
        baseline_selection_score=90.0,
        current_selection_score=72.0,
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(
            interval_days=30,
            minimum_performance_drop=0.10,
        ),
        now=NOW,
    )

    assert decision.should_retrain is True
    assert RetrainingReason.PERFORMANCE_DROP in decision.reasons
    assert decision.performance_drop == 0.20
    assert decision.priority == 75


def test_data_drift_has_high_priority() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="drift",
        profile_version="1.0",
        last_trained_at=NOW - timedelta(days=1),
        drift_score=0.35,
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(
            interval_days=30,
            minimum_drift_score=0.20,
        ),
        now=NOW,
    )

    assert decision.should_retrain is True
    assert RetrainingReason.DATA_DRIFT in decision.reasons
    assert decision.priority == 85


def test_manual_request_has_highest_priority() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="manual",
        profile_version="1.0",
        last_trained_at=NOW,
        manual_request=True,
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(),
        now=NOW,
    )

    assert decision.should_retrain is True
    assert RetrainingReason.MANUAL in decision.reasons
    assert decision.priority == 100


def test_failed_model_can_be_retried() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="failed",
        profile_version="1.0",
        last_trained_at=NOW,
        model_failed=True,
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(
            retry_failed_models=True,
        ),
        now=NOW,
    )

    assert decision.should_retrain is True
    assert RetrainingReason.FAILED_MODEL in decision.reasons
    assert decision.priority == 90


def test_disabled_policy_prevents_retraining() -> None:
    scheduler = RetrainingScheduler()

    candidate = RetrainingCandidate(
        profile_name="disabled",
        profile_version="1.0",
        last_trained_at=None,
        manual_request=True,
        model_failed=True,
        drift_score=1.0,
    )

    decision = scheduler.evaluate(
        candidate=candidate,
        policy=RetrainingPolicy(enabled=False),
        now=NOW,
    )

    assert decision.should_retrain is False
    assert decision.reasons == ()
    assert decision.priority == 0


def test_candidates_are_prioritized() -> None:
    scheduler = RetrainingScheduler()



    candidates = [
        RetrainingCandidate(
            profile_name="scheduled",
            profile_version="1.0",
            last_trained_at=NOW - timedelta(days=10),
        ),
        RetrainingCandidate(
            profile_name="drift",
            profile_version="1.0",
            last_trained_at=NOW - timedelta(days=1),
            drift_score=0.50,
        ),
        RetrainingCandidate(
            profile_name="manual",
            profile_version="1.0",
            last_trained_at=NOW,
            manual_request=True,
        ),
    ]

    result = scheduler.prioritize(

        candidates=candidates,
        policies={},
        now=NOW,
    )

    assert [
        candidate.profile_name
        for candidate, _decision in result
    ] == [
        "manual",
        "drift",
        "scheduled",
    ]