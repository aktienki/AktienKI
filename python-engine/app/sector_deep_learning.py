from __future__ import annotations

from dataclasses import dataclass, replace
from datetime import datetime
from math import sqrt
from statistics import fmean, pstdev
from typing import Mapping, Sequence


@dataclass(frozen=True)
class SectorObservation:
    timestamp: datetime
    close: float
    features: tuple[float, ...]
    member_count: int
    universe_size: int


def aggregate_sector_history(
    histories: Mapping[str, Sequence], *, minimum_members: int = 5,
) -> list[SectorObservation]:
    """Create a causal equal-weighted sector series from stored stock bars."""
    closes = {
        symbol: {
            bar.timestamp if isinstance(bar.timestamp, datetime) else datetime.fromisoformat(str(bar.timestamp)): float(bar.close)
            for bar in bars if float(bar.close) > 0
        }
        for symbol, bars in histories.items()
    }
    closes = {symbol: values for symbol, values in closes.items() if len(values) >= 2}
    if len(closes) < minimum_members:
        return []
    dates = sorted({stamp for values in closes.values() for stamp in values})
    previous: dict[str, float] = {}
    price_history: dict[str, list[float]] = {symbol: [] for symbol in closes}
    synthetic_close, raw = 100.0, []
    for stamp in dates:
        returns = []
        for symbol, values in closes.items():
            value = values.get(stamp)
            if value is None:
                continue
            price_history[symbol].append(value)
            prior = previous.get(symbol)
            if prior is not None and prior > 0:
                returns.append(value / prior - 1.0)
            previous[symbol] = value
        if len(returns) < minimum_members:
            continue
        daily_return = fmean(returns)
        synthetic_close *= max(0.01, 1.0 + daily_return)
        above = {}
        for window in (20, 50, 200):
            eligible = [history for history in price_history.values() if len(history) >= window]
            above[window] = (
                sum(history[-1] > fmean(history[-window:]) for history in eligible) / len(eligible)
                if eligible else 0.5
            )
        raw.append({
            "timestamp": stamp, "close": synthetic_close, "return": daily_return,
            "positive_breadth": sum(value > 0 for value in returns) / len(returns),
            "dispersion": pstdev(returns) if len(returns) > 1 else 0.0,
            "member_count": len(returns), "above": above,
        })
    observations, universe_size = [], len(closes)
    for index, row in enumerate(raw):
        if index < 200:
            continue
        sector_closes = [item["close"] for item in raw[:index + 1]]
        sector_returns = [item["return"] for item in raw[max(0, index - 19):index + 1]]
        period_return = lambda days: sector_closes[-1] / sector_closes[-1 - days] - 1.0
        observations.append(SectorObservation(
            timestamp=row["timestamp"], close=row["close"],
            features=(
                row["return"], period_return(5), period_return(20), period_return(60),
                pstdev(sector_returns) * sqrt(252) if len(sector_returns) > 1 else 0.0,
                row["positive_breadth"], row["above"][20], row["above"][50], row["above"][200],
                row["dispersion"], row["member_count"] / universe_size,
            ),
            member_count=row["member_count"], universe_size=universe_size,
        ))
    return observations


def add_cross_sector_context(
    sectors: Mapping[str, Sequence[SectorObservation]],
) -> dict[str, list[SectorObservation]]:
    by_date: dict[datetime, list[tuple[float, float, float, float]]] = {}
    for observations in sectors.values():
        for item in observations:
            by_date.setdefault(item.timestamp, []).append(
                (item.features[0], item.features[1], item.features[2], item.features[4])
            )
    market = {
        stamp: tuple(fmean(values[index] for values in rows) for index in range(4))
        for stamp, rows in by_date.items()
    }
    return {
        sector: [
            replace(item, features=item.features + market[item.timestamp] + tuple(
                item.features[index] - market[item.timestamp][index] for index in range(3)
            ))
            for item in observations
        ]
        for sector, observations in sectors.items()
    }
