from __future__ import annotations

import numpy as np
import pandas as pd

from app.strategies.score_exit import score_exit_replay


def replay(predictions, scores, *, maximum_holding_days=40):
    size = len(predictions)
    return score_exit_replay(
        np.asarray(predictions, dtype=float),
        np.asarray(scores, dtype=float),
        pd.date_range("2025-01-01", periods=size, freq="B"),
        np.linspace(100.0, 120.0, size),
        np.ones(size, dtype=bool),
        maximum_holding_days=maximum_holding_days,
        transaction_cost=0.0,
    )


def test_entry_requires_return_and_score_gate():
    result = replay([0.02, 0.02, 0.0, 0.02, 0.02], [5.4, 5.6, 4.9, 5.5, 5.6])
    assert result["trades"] == 2
    assert result["trades_detail"][0]["entry_date"] == "2025-01-02"
    assert result["trades_detail"][1]["entry_date"] == "2025-01-06"


def test_peak_score_decline_uses_highest_score_since_entry():
    result = replay([0.02] * 6, [5.5, 6.0, 6.8, 6.2, 5.7, 5.6])
    trade = result["trades_detail"][0]
    assert trade["reason"] == "peak_score_decline"
    assert trade["holding_days"] == 4
    assert trade["peak_score"] == 6.8


def test_absolute_score_exit_does_not_require_a_crossing():
    result = replay([0.02] * 4, [5.5, 5.2, 4.9, 5.1])
    trade = result["trades_detail"][0]
    assert trade["reason"] == "absolute_score"
    assert trade["holding_days"] == 2


def test_maximum_holding_period_is_enforced():
    result = replay([0.02] * 8, [5.5, 5.7, 5.8, 5.9, 6.0, 6.1, 6.2, 6.3], maximum_holding_days=3)
    trade = result["trades_detail"][0]
    assert trade["reason"] == "max_hold"
    assert trade["holding_days"] == 3


def test_future_scores_do_not_change_an_existing_exit():
    base = replay([0.02] * 5, [5.5, 6.2, 5.1, 7.0, 7.0])
    changed_future = replay([0.02] * 5, [5.5, 6.2, 5.1, 0.0, 0.0])
    assert base["trades_detail"][0]["exit_date"] == changed_future["trades_detail"][0]["exit_date"]
    assert base["trades_detail"][0]["reason"] == "peak_score_decline"
