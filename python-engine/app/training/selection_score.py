from __future__ import annotations

from dataclasses import dataclass

from app.training.metrics import (
    cagr,
    max_drawdown,
    profit_factor,
    sharpe_ratio,
    sortino_ratio,
    win_rate,
)


@dataclass(slots=True)
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
        weight_profit_factor: float = 0.30,
        weight_sharpe: float = 0.20,
        weight_sortino: float = 0.15,
        weight_cagr: float = 0.15,
        weight_drawdown: float = 0.10,
        weight_win_rate: float = 0.10,
    ):

        self.weight_profit_factor = weight_profit_factor
        self.weight_sharpe = weight_sharpe
        self.weight_sortino = weight_sortino
        self.weight_cagr = weight_cagr
        self.weight_drawdown = weight_drawdown
        self.weight_win_rate = weight_win_rate

    @staticmethod
    def _normalize(value: float, maximum: float) -> float:

        if maximum <= 0:
            return 0.0

        return min(max(value / maximum, 0.0), 1.0)

    def calculate(
        self,
        returns: list[float],
        equity_curve: list[float],
    ) -> SelectionResult:

        pf = profit_factor(returns)
        sr = sharpe_ratio(returns)
        so = sortino_ratio(returns)
        cg = cagr(equity_curve)
        dd = max_drawdown(equity_curve)
        wr = win_rate(returns)

        pf_score = self._normalize(pf, 3.0)
        sharpe_score = self._normalize(sr, 3.0)
        sortino_score = self._normalize(so, 4.0)
        cagr_score = self._normalize(cg, 0.50)
        drawdown_score = 1.0 - self._normalize(dd, 0.50)
        winrate_score = wr

        score = (
            pf_score * self.weight_profit_factor
            + sharpe_score * self.weight_sharpe
            + sortino_score * self.weight_sortino
            + cagr_score * self.weight_cagr
            + drawdown_score * self.weight_drawdown
            + winrate_score * self.weight_win_rate
        ) * 100

        return SelectionResult(
            score=round(score, 2),
            profit_factor=pf,
            sharpe=sr,
            sortino=so,
            cagr=cg,
            drawdown=dd,
            win_rate=wr,
        )