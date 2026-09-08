from __future__ import annotations

import numpy as np
import pandas as pd


def _trade_metrics(returns: list[float]) -> dict[str, float | int | None]:
    gains = sum(value for value in returns if value > 0)
    losses = abs(sum(value for value in returns if value < 0))
    return {
        "trades": len(returns),
        "hit_rate": round(float(np.mean(np.asarray(returns) > 0)) * 100, 2) if returns else None,
        "profit_factor": round(gains / losses, 3) if losses > 0 else None,
        "average_return_percent": round(float(np.mean(returns)) * 100, 3) if returns else None,
        "cumulative_return_percent": round(float(np.prod(1.0 + np.asarray(returns)) - 1.0) * 100, 3) if returns else None,
    }


def score_exit_replay(
    predictions: np.ndarray,
    scores: np.ndarray,
    dates: pd.DatetimeIndex,
    closes: np.ndarray,
    mask: np.ndarray,
    *,
    entry_return: float = 0.01,
    entry_score: float = 5.5,
    absolute_exit_score: float | None = 5.0,
    peak_score_decline: float | None = 1.0,
    maximum_holding_days: int = 40,
    transaction_cost: float = 0.005,
) -> dict:
    """Replay one causal position using point-in-time scores on a 0-10 scale."""
    arrays = (predictions, scores, closes, mask)
    if any(len(value) != len(dates) for value in arrays):
        raise ValueError("predictions, scores, closes, mask and dates must align")
    if maximum_holding_days < 1:
        raise ValueError("maximum_holding_days must be positive")

    eligible = (predictions >= entry_return) & (scores >= entry_score) & mask
    entries = eligible & ~np.r_[False, eligible[:-1]]
    eligible_positions = np.flatnonzero(mask)
    if not len(eligible_positions):
        return {**_trade_metrics([]), "maximum_drawdown_percent": 0.0, "average_holding_days": None, "trades_detail": []}

    final_position = int(eligible_positions[-1])
    next_free = 0
    trades: list[dict] = []
    for entry in np.flatnonzero(entries):
        if entry < next_free or not np.isfinite(closes[entry]):
            continue
        last = min(entry + maximum_holding_days, final_position)
        if last <= entry:
            continue
        exit_position = last
        reason = "max_hold"
        peak_score = float(scores[entry])
        for position in range(entry + 1, last + 1):
            current_score = float(scores[position])
            peak_score = max(peak_score, current_score)
            if absolute_exit_score is not None and current_score < absolute_exit_score:
                exit_position, reason = position, "absolute_score"
                break
            if peak_score_decline is not None and peak_score - current_score >= peak_score_decline:
                exit_position, reason = position, "peak_score_decline"
                break
        if not np.isfinite(closes[exit_position]):
            continue
        net_return = float(closes[exit_position] / closes[entry] - 1.0 - transaction_cost)
        trades.append({
            "entry_date": str(pd.Timestamp(dates[entry]).date()),
            "exit_date": str(pd.Timestamp(dates[exit_position]).date()),
            "entry_score": round(float(scores[entry]), 3),
            "peak_score": round(peak_score, 3),
            "exit_score": round(float(scores[exit_position]), 3),
            "holding_days": int(exit_position - entry),
            "reason": reason,
            "net_return_percent": round(net_return * 100, 3),
        })
        next_free = exit_position + 1

    values = [float(item["net_return_percent"]) / 100 for item in trades]
    equity = peak_equity = 1.0
    maximum_drawdown = 0.0
    for value in values:
        equity *= max(0.000001, 1.0 + value)
        peak_equity = max(peak_equity, equity)
        maximum_drawdown = max(maximum_drawdown, (peak_equity - equity) / peak_equity)
    return {
        **_trade_metrics(values),
        "maximum_drawdown_percent": round(maximum_drawdown * 100, 3),
        "average_holding_days": round(float(np.mean([item["holding_days"] for item in trades])), 2) if trades else None,
        "trades_detail": trades,
    }
