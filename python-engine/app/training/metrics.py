from __future__ import annotations

import math
from statistics import mean, stdev
from typing import Sequence


def profit_factor(returns: Sequence[float]) -> float:
    """
    Profit Factor = Bruttogewinne / Bruttoverluste
    """

    profits = sum(r for r in returns if r > 0)
    losses = abs(sum(r for r in returns if r < 0))

    if losses == 0:
        return math.inf if profits > 0 else 0.0

    return profits / losses


def max_drawdown(equity_curve: Sequence[float]) -> float:
    """
    Maximum Drawdown der Equity Curve.
    """

    if not equity_curve:
        return 0.0

    peak = equity_curve[0]
    worst = 0.0

    for value in equity_curve:
        if value > peak:
            peak = value

        drawdown = (peak - value) / peak if peak else 0.0
        worst = max(worst, drawdown)

    return worst


def sharpe_ratio(
    returns: Sequence[float],
    risk_free_rate: float = 0.0,
    periods_per_year: int = 252,
) -> float:
    """
    Annualisierte Sharpe Ratio.
    """

    if len(returns) < 2:
        return 0.0

    excess = [r - risk_free_rate / periods_per_year for r in returns]

    sigma = stdev(excess)

    if sigma == 0:
        return 0.0

    return (mean(excess) / sigma) * math.sqrt(periods_per_year)


def sortino_ratio(
    returns: Sequence[float],
    risk_free_rate: float = 0.0,
    periods_per_year: int = 252,
) -> float:
    """
    Annualisierte Sortino Ratio.
    """

    if len(returns) < 2:
        return 0.0

    excess = [r - risk_free_rate / periods_per_year for r in returns]

    downside = [r for r in excess if r < 0]

    if len(downside) < 2:
        return math.inf

    downside_dev = stdev(downside)

    if downside_dev == 0:
        return math.inf

    return (mean(excess) / downside_dev) * math.sqrt(periods_per_year)


def cagr(
    equity_curve: Sequence[float],
    periods_per_year: int = 252,
) -> float:
    """
    Compound Annual Growth Rate.
    """

    if len(equity_curve) < 2:
        return 0.0

    start = equity_curve[0]
    end = equity_curve[-1]

    if start <= 0:
        return 0.0

    years = len(equity_curve) / periods_per_year

    return (end / start) ** (1 / years) - 1


def expectancy(
    returns: Sequence[float],
) -> float:
    """
    Durchschnittlicher Erwartungswert pro Trade.
    """

    if not returns:
        return 0.0

    return mean(returns)


def win_rate(
    returns: Sequence[float],
) -> float:
    """
    Trefferquote.
    """

    if not returns:
        return 0.0

    wins = len([r for r in returns if r > 0])

    return wins / len(returns)


def average_win(
    returns: Sequence[float],
) -> float:

    wins = [r for r in returns if r > 0]

    return mean(wins) if wins else 0.0


def average_loss(
    returns: Sequence[float],
) -> float:

    losses = [r for r in returns if r < 0]

    return mean(losses) if losses else 0.0


def volatility(
    returns: Sequence[float],
    periods_per_year: int = 252,
) -> float:

    if len(returns) < 2:
        return 0.0

    return stdev(returns) * math.sqrt(periods_per_year)