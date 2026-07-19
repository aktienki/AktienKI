from __future__ import annotations

from dataclasses import dataclass
import math
from typing import Sequence

from app.training.metrics import (
    cagr,
    max_drawdown,
    profit_factor,
    sharpe_ratio,
    sortino_ratio,
    win_rate,
)


@dataclass(frozen=True, slots=True)
class SelectionResult:
    score: float
    profit_factor: float
    sharpe: float
    sortino: float
    cagr: float
    drawdown: float
    win_rate: float


class SelectionScore:
    def __init__(
        self,
        *,
        weight_profit_factor: float = 0.30,
        weight_sharpe: float = 0.20,
        weight_sortino: float = 0.15,
        weight_cagr: float = 0.15,
        weight_drawdown: float = 0.10,
        weight_win_rate: float = 0.10,
    ) -> None:
        self.weight_profit_factor = weight_profit_factor
        self.weight_sharpe = weight_sharpe
        self.weight_sortino = weight_sortino
        self.weight_cagr = weight_cagr
        self.weight_drawdown = weight_drawdown
        self.weight_win_rate = weight_win_rate

        total_weight = (
            weight_profit_factor
            + weight_sharpe
            + weight_sortino
            + weight_cagr
            + weight_drawdown
            + weight_win_rate
        )

        if not math.isclose(total_weight, 1.0, abs_tol=1e-9):
            raise ValueError("Die Gewichte müssen zusammen 1.0 ergeben.")

    @staticmethod
    def _normalize(
        value: float,
        maximum: float,
    ) -> float:
        if maximum <= 0:
            raise ValueError("maximum muss größer als 0 sein.")

        if math.isnan(value):
            return 0.0

        if math.isinf(value):
            return 1.0 if value > 0 else 0.0

        return min(max(value / maximum, 0.0), 1.0)

    def calculate(
        self,
        returns: Sequence[float],
        equity_curve: Sequence[float],
        *,
        periods_per_year: int = 252,
    ) -> SelectionResult:
        pf = profit_factor(returns)
        sharpe = sharpe_ratio(
            returns,
            periods_per_year=periods_per_year,
        )
        sortino = sortino_ratio(
            returns,
            periods_per_year=periods_per_year,
        )
        annual_growth = cagr(
            equity_curve,
            periods_per_year=periods_per_year,
        )
        drawdown = max_drawdown(equity_curve)
        hit_rate = win_rate(returns)

        pf_score = self._normalize(pf, 3.0)
        sharpe_score = self._normalize(sharpe, 3.0)
        sortino_score = self._normalize(sortino, 4.0)
        cagr_score = self._normalize(annual_growth, 0.50)
        drawdown_score = 1.0 - self._normalize(drawdown, 0.50)
        win_rate_score = min(max(hit_rate, 0.0), 1.0)

        score = (
            pf_score * self.weight_profit_factor
            + sharpe_score * self.weight_sharpe
            + sortino_score * self.weight_sortino
            + cagr_score * self.weight_cagr
            + drawdown_score * self.weight_drawdown
            + win_rate_score * self.weight_win_rate
        ) * 100.0

        return SelectionResult(
            score=round(score, 2),
            profit_factor=pf,
            sharpe=sharpe,
            sortino=sortino,
            cagr=annual_growth,
            drawdown=drawdown,
            win_rate=hit_rate,
        )