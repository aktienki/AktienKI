from __future__ import annotations

import math

import pytest

from app.training.metrics import (
    cagr,
    max_drawdown,
    profit_factor,
    sharpe_ratio,
    sortino_ratio,
    win_rate,
)


def test_profit_factor() -> None:
    returns = [0.10, -0.05, 0.20, -0.05]

    assert profit_factor(returns) == pytest.approx(3.0)


def test_profit_factor_without_losses() -> None:
    assert math.isinf(profit_factor([0.01, 0.02]))


def test_max_drawdown() -> None:
    equity_curve = [100.0, 120.0, 90.0, 110.0]

    assert max_drawdown(equity_curve) == pytest.approx(0.25)


def test_sharpe_ratio_is_positive() -> None:
    returns = [0.01, 0.02, -0.005, 0.015, 0.01]

    result = sharpe_ratio(
        returns,
        periods_per_year=252,
    )

    assert result > 0.0


def test_sortino_ratio_is_positive() -> None:
    returns = [0.01, 0.02, -0.005, 0.015, 0.01]

    result = sortino_ratio(
        returns,
        periods_per_year=252,
    )

    assert result > 0.0


def test_cagr() -> None:
    equity_curve = [100.0, 110.0]

    result = cagr(
        equity_curve,
        periods_per_year=1,
    )

    assert result == pytest.approx(0.10)


def test_win_rate() -> None:
    returns = [0.01, -0.01, 0.02, 0.03]

    assert win_rate(returns) == pytest.approx(0.75)