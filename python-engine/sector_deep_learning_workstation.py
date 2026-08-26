from __future__ import annotations

from dataclasses import dataclass, replace
from datetime import datetime
from math import sqrt
from statistics import fmean, pstdev
from typing import Iterable, Mapping, Sequence


HORIZONS = (5, 10, 20, 40, 60)
FEATURE_NAMES = (
    "return_1d",
    "return_5d",
    "return_20d",
    "return_60d",
    "volatility_20d",
    "positive_breadth",
    "above_sma_20",
    "above_sma_50",
    "above_sma_200",
    "return_dispersion",
    "member_coverage",
    "market_return_1d",
    "market_return_5d",
    "market_return_20d",
    "market_volatility_20d",
    "relative_return_1d",
    "relative_return_5d",
    "relative_return_20d",
)


@dataclass(frozen=True)
class SectorObservation:
    timestamp: datetime
    close: float
    features: tuple[float, ...]
    member_count: int
    universe_size: int


@dataclass(frozen=True)
class SectorSample:
    sector: str
    timestamp: datetime
    sequence: tuple[tuple[float, ...], ...]
    future_returns: tuple[float, ...]


def _timestamp(bar) -> datetime:
    value = bar.timestamp
    parsed = value if isinstance(value, datetime) else datetime.fromisoformat(str(value))
    # Database histories can contain a mix of DATE values (naive midnight) and
    # TIMESTAMPTZ values. Use the quoted calendar date as a UTC-neutral daily
    # key so it remains sortable without moving an exchange day across midnight.
    if parsed.tzinfo is not None:
        parsed = parsed.replace(tzinfo=None)
    return parsed.replace(hour=0, minute=0, second=0, microsecond=0)


def _close(bar) -> float:
    return float(bar.close)


def aggregate_sector_history(
    histories: Mapping[str, Sequence],
    *,
    minimum_members: int = 5,
) -> list[SectorObservation]:
    """Build a causal, equal-weighted daily sector index and breadth features."""
    if minimum_members < 2:
        raise ValueError("minimum_members must be at least 2")
    closes: dict[str, dict[datetime, float]] = {
        symbol: {_timestamp(bar): _close(bar) for bar in bars if _close(bar) > 0}
        for symbol, bars in histories.items()
    }
    closes = {symbol: values for symbol, values in closes.items() if len(values) >= 2}
    if len(closes) < minimum_members:
        return []

    dates = sorted({stamp for values in closes.values() for stamp in values})
    previous: dict[str, float] = {}
    price_history: dict[str, list[float]] = {symbol: [] for symbol in closes}
    synthetic_close = 100.0
    raw: list[dict] = []
    for stamp in dates:
        returns: list[float] = []
        active_closes: dict[str, float] = {}
        for symbol, values in closes.items():
            value = values.get(stamp)
            if value is None:
                continue
            active_closes[symbol] = value
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
            eligible = [
                history for history in price_history.values()
                if len(history) >= window
            ]
            above[window] = (
                sum(history[-1] > fmean(history[-window:]) for history in eligible)
                / len(eligible)
                if eligible else 0.5
            )
        raw.append({
            "timestamp": stamp,
            "close": synthetic_close,
            "return": daily_return,
            "positive_breadth": sum(value > 0 for value in returns) / len(returns),
            "dispersion": pstdev(returns) if len(returns) > 1 else 0.0,
            "active_closes": active_closes,
            "member_count": len(returns),
            "above": above,
        })

    observations: list[SectorObservation] = []
    universe_size = len(closes)
    for index, row in enumerate(raw):
        if index < 200:
            continue
        sector_closes = [item["close"] for item in raw[: index + 1]]
        sector_returns = [item["return"] for item in raw[max(0, index - 19): index + 1]]

        def period_return(days: int) -> float:
            return sector_closes[-1] / sector_closes[-1 - days] - 1.0

        features = (
            row["return"],
            period_return(5),
            period_return(20),
            period_return(60),
            pstdev(sector_returns) * sqrt(252) if len(sector_returns) > 1 else 0.0,
            row["positive_breadth"],
            row["above"][20],
            row["above"][50],
            row["above"][200],
            row["dispersion"],
            row["member_count"] / universe_size,
        )
        observations.append(SectorObservation(
            timestamp=row["timestamp"],
            close=row["close"],
            features=features,
            member_count=row["member_count"],
            universe_size=universe_size,
        ))
    return observations


def build_sector_samples(
    sector: str,
    observations: Sequence[SectorObservation],
    *,
    sequence_length: int = 60,
    horizons: tuple[int, ...] = HORIZONS,
) -> list[SectorSample]:
    if sequence_length < 2:
        raise ValueError("sequence_length must be at least 2")
    maximum_horizon = max(horizons)
    samples = []
    for index in range(sequence_length - 1, len(observations) - maximum_horizon):
        current = observations[index]
        future_returns = tuple(
            observations[index + horizon].close / current.close - 1.0
            for horizon in horizons
        )
        samples.append(SectorSample(
            sector=sector,
            timestamp=current.timestamp,
            sequence=tuple(
                item.features
                for item in observations[index - sequence_length + 1:index + 1]
            ),
            future_returns=future_returns,
        ))
    return samples


def add_cross_sector_context(
    sectors: Mapping[str, Sequence[SectorObservation]],
) -> dict[str, list[SectorObservation]]:
    """Append causal broad-market and sector-relative return features by date."""
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
            replace(
                item,
                features=item.features + market[item.timestamp] + tuple(
                    item.features[index] - market[item.timestamp][index]
                    for index in range(3)
                ),
            )
            for item in observations
        ]
        for sector, observations in sectors.items()
    }


def exclude_extreme_volatility_training_samples(
    samples: Sequence[SectorSample],
    *,
    quantile: float = 0.95,
) -> tuple[list[SectorSample], float]:
    """Remove only the highest causal market-volatility regime from training."""
    if not 0.5 < quantile < 1.0:
        raise ValueError("quantile must be between 0.5 and 1.0")
    if not samples:
        return [], 0.0
    values = sorted(float(sample.sequence[-1][14]) for sample in samples)
    threshold = values[min(len(values) - 1, int((len(values) - 1) * quantile))]
    return [
        sample for sample in samples
        if float(sample.sequence[-1][14]) <= threshold
    ], threshold


def relative_sector_targets(
    samples: Sequence[SectorSample],
) -> list[SectorSample]:
    """Convert absolute future returns to cross-sector excess returns by date."""
    by_date: dict[datetime, list[tuple[float, ...]]] = {}
    for sample in samples:
        by_date.setdefault(sample.timestamp, []).append(sample.future_returns)
    averages = {
        stamp: tuple(
            fmean(values[index] for values in rows)
            for index in range(len(HORIZONS))
        )
        for stamp, rows in by_date.items()
    }
    return [
        replace(
            sample,
            future_returns=tuple(
                value - averages[sample.timestamp][index]
                for index, value in enumerate(sample.future_returns)
            ),
        )
        for sample in samples
    ]


def expanding_year_folds(
    samples: Iterable[SectorSample],
    *,
    minimum_training_years: int = 5,
) -> list[tuple[list[int], list[int]]]:
    values = list(samples)
    years = sorted({sample.timestamp.year for sample in values})
    folds = []
    for test_year in years[minimum_training_years:]:
        train = [i for i, sample in enumerate(values) if sample.timestamp.year < test_year]
        test = [i for i, sample in enumerate(values) if sample.timestamp.year == test_year]
        if train and test:
            folds.append((train, test))
    return folds
