from __future__ import annotations

import pytest

from app.training.evaluator import StrategyEvaluator
from app.training.optimizer import SelectionOptimizer
from app.training.selection_score import SelectionResult


def _result(score: float) -> SelectionResult:
    return SelectionResult(
        score=score,
        profit_factor=1.5,
        sharpe=1.2,
        sortino=1.4,
        cagr=0.18,
        drawdown=0.12,
        win_rate=0.58,
    )


def test_optimizer_selects_highest_score() -> None:
    optimizer = SelectionOptimizer()

    candidates = [
        _result(72.5),
        _result(91.2),
        _result(84.4),
    ]

    result = optimizer.select_best(candidates)

    assert result.best.score == 91.2
    assert result.best_index == 1
    assert [item.score for item in result.ranking] == [
        91.2,
        84.4,
        72.5,
    ]


def test_optimizer_rejects_empty_candidates() -> None:
    optimizer = SelectionOptimizer()

    with pytest.raises(
        ValueError,
        match="Keine Kandidaten vorhanden",
    ):
        optimizer.select_best([])


def test_optimizer_ranks_candidates_descending() -> None:
    optimizer = SelectionOptimizer()

    candidates = [
        _result(50.0),
        _result(80.0),
        _result(65.0),
    ]

    ranking = optimizer.rank(candidates)

    assert [item.score for item in ranking] == [
        80.0,
        65.0,
        50.0,
    ]


def test_strategy_evaluator_calculates_selection_result() -> None:
    evaluator = StrategyEvaluator()

    returns = [
        0.02,
        -0.01,
        0.03,
        0.01,
        -0.005,
    ]
    equity_curve = [
        100.0,
        102.0,
        100.98,
        104.0094,
        105.049494,
        104.52424653,
    ]

    result = evaluator.evaluate(
        returns=returns,
        equity_curve=equity_curve,
        periods_per_year=252,
    )

    assert isinstance(result, SelectionResult)
    assert 0.0 <= result.score <= 100.0
    assert result.profit_factor > 0.0
    assert result.win_rate == pytest.approx(0.6)


def test_strategy_evaluator_selects_best_candidate() -> None:
    evaluator = StrategyEvaluator()

    candidates = [
        _result(60.0),
        _result(88.0),
        _result(75.0),
    ]

    result = evaluator.select_best(candidates)

    assert result.best.score == 88.0
    assert result.best_index == 1