from __future__ import annotations

import argparse
import json
import math
import warnings
from bisect import bisect_right
from datetime import UTC
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.ensemble import HistGradientBoostingRegressor
from sklearn.metrics import mean_absolute_error
from sklearn.preprocessing import StandardScaler
from statsmodels.tsa.arima.model import ARIMA

warnings.filterwarnings("ignore", module="statsmodels")

from app.cli.main import _import
from app.database.connection import Database
from app.features import FeatureBuilder
from app.features.profile import triple_daily_macro_profile
from app.market.benchmark import resolve_benchmark_symbol
from app.repositories.instrument_repository import InstrumentRepository


HORIZON = 20
OOS_LOOKBACK_YEARS = 7
VALIDATION_YEARS = 3
TRANSACTION_COST = 0.005


def aligned_benchmark(bars, benchmark_bars) -> list[float]:
    points = sorted(
        (
            bar.timestamp.replace(tzinfo=UTC) if bar.timestamp.tzinfo is None else bar.timestamp.astimezone(UTC),
            float(bar.close),
        )
        for bar in benchmark_bars
    )
    timestamps = [point[0] for point in points]
    closes = [point[1] for point in points]
    return [
        closes[max(0, bisect_right(timestamps, bar.timestamp.replace(tzinfo=UTC) if bar.timestamp.tzinfo is None else bar.timestamp.astimezone(UTC)) - 1)]
        for bar in bars
    ]


def rolling_slope(values: pd.Series, window: int) -> pd.Series:
    x = np.arange(window, dtype=float)
    x_centered = x - x.mean()
    denominator = float(np.square(x_centered).sum())
    return values.rolling(window).apply(
        lambda y: float(np.dot(np.asarray(y) - np.asarray(y).mean(), x_centered) / denominator),
        raw=False,
    )


def rolling_r2(values: pd.Series, window: int) -> pd.Series:
    x = np.arange(window, dtype=float)
    return values.rolling(window).apply(
        lambda y: float(np.corrcoef(x, np.asarray(y))[0, 1] ** 2) if np.std(y) > 0 else 0.0,
        raw=False,
    )


def rolling_ar1(values: pd.Series, window: int) -> pd.Series:
    def coefficient(window_values) -> float:
        y = np.asarray(window_values, dtype=float)
        left, right = y[:-1], y[1:]
        if np.std(left) == 0 or np.std(right) == 0:
            return 0.0
        return float(np.clip(np.corrcoef(left, right)[0, 1], -0.98, 0.98))

    return values.rolling(window).apply(coefficient, raw=False)


def timeseries_features(frame: pd.DataFrame) -> pd.DataFrame:
    close = frame["close"]
    log_close = np.log(close)
    returns = log_close.diff()
    result = pd.DataFrame(index=frame.index)
    for lag in (1, 2, 5, 10, 20):
        result[f"TS__LOG_RETURN_{lag}"] = log_close.diff(lag)
    for window in (20, 60, 120):
        slope = rolling_slope(log_close, window)
        result[f"TS__TREND_FORECAST_20_{window}"] = slope * HORIZON
        result[f"TS__TREND_R2_{window}"] = rolling_r2(log_close, window)
    result["TS__AR1_60"] = rolling_ar1(returns, 60)
    rolling_mean = returns.rolling(60).mean()
    phi = result["TS__AR1_60"]
    latest = returns
    geometric = phi * (1.0 - phi.pow(HORIZON)) / (1.0 - phi).replace(0, np.nan)
    result["TS__AR_FORECAST_20_60"] = rolling_mean * HORIZON + (latest - rolling_mean) * geometric
    result["TS__VOL_RATIO_5_20"] = returns.rolling(5).std() / returns.rolling(20).std().replace(0, np.nan)
    result["TS__POSITIVE_SHARE_20"] = (returns > 0).rolling(20).mean()
    result["TS__DRAWDOWN_60"] = close / close.rolling(60).max() - 1.0
    return result.replace([np.inf, -np.inf], np.nan)


def arima_features(frame: pd.DataFrame, *, window: int = 504, stride: int = 5) -> pd.DataFrame:
    """Causal weekly-refreshed ARIMA(1,1,1) 20-day forecast features."""
    log_close = np.log(frame["close"])
    result = pd.DataFrame(
        index=frame.index,
        columns=["ARIMA__FORECAST_RETURN_20", "ARIMA__FORECAST_SE_20", "ARIMA__RESIDUAL_VOL"],
        dtype=float,
    )
    for position in range(window - 1, len(frame), stride):
        history = log_close.iloc[position - window + 1: position + 1]
        try:
            fitted = ARIMA(history.to_numpy(), order=(1, 1, 1), trend="t").fit(
                method_kwargs={"warn_convergence": False}
            )
            forecast = fitted.get_forecast(steps=HORIZON)
            predicted_log = float(np.asarray(forecast.predicted_mean)[-1])
            standard_error = float(np.asarray(forecast.se_mean)[-1])
            residual_volatility = float(np.std(np.asarray(fitted.resid)[1:]))
            result.iloc[position] = [
                math.exp(predicted_log - float(history.iloc[-1])) - 1.0,
                standard_error,
                residual_volatility,
            ]
        except (ValueError, np.linalg.LinAlgError):
            continue
    return result.ffill().replace([np.inf, -np.inf], np.nan)


def context_series(database: Database, scope_type: str, scope_key: str) -> pd.Series:
    rows = database.fetch_all(
        """SELECT prediction_date, score
             FROM market_context_predictions
            WHERE scope_type=%s AND scope_key=%s
            ORDER BY prediction_date""",
        (scope_type, scope_key),
    )
    if not rows:
        return pd.Series(dtype=float)
    index = pd.to_datetime([row["prediction_date"] for row in rows])
    return pd.Series([float(row["score"]) / 10.0 for row in rows], index=index)


def align_context(index: pd.DatetimeIndex, values: pd.Series) -> tuple[pd.Series, pd.Series]:
    if values.empty:
        return pd.Series(0.5, index=index), pd.Series(0.0, index=index)
    aligned = values.reindex(index.union(values.index)).sort_index().ffill().reindex(index)
    available = aligned.notna().astype(float)
    return aligned.fillna(0.5), available


def entry_returns(predictions: np.ndarray, actual: np.ndarray, threshold: float = 0.01) -> list[float]:
    accepted = predictions >= threshold
    previous = False
    returns: list[float] = []
    for is_accepted, realized in zip(accepted, actual, strict=True):
        if is_accepted and not previous:
            returns.append(float(realized))
        previous = bool(is_accepted)
    return returns


def trade_metrics(returns: list[float]) -> dict[str, float | int | None]:
    gains = sum(value for value in returns if value > 0)
    losses = abs(sum(value for value in returns if value < 0))
    return {
        "trades": len(returns),
        "hit_rate": round(float(np.mean(np.asarray(returns) > 0)) * 100, 2) if returns else None,
        "profit_factor": round(gains / losses, 3) if losses > 0 else None,
        "average_return_percent": round(float(np.mean(returns)) * 100, 3) if returns else None,
        "cumulative_return_percent": round(float(np.prod(1.0 + np.asarray(returns)) - 1.0) * 100, 3) if returns else None,
    }


def metrics(predictions: np.ndarray, actual: np.ndarray) -> dict:
    gross = entry_returns(predictions, actual)
    return {
        "samples": int(len(actual)),
        "direction_accuracy": round(float(np.mean((predictions >= 0) == (actual >= 0))) * 100, 2),
        "mae_percent": round(float(mean_absolute_error(actual, predictions)) * 100, 3),
        "buy_signal_changes": len(gross),
        "gross": trade_metrics(gross),
        "net_cost_scenarios": {
            "0.10_percent": trade_metrics([value - 0.001 for value in gross]),
            "0.25_percent": trade_metrics([value - 0.0025 for value in gross]),
            "0.50_percent": trade_metrics([value - 0.005 for value in gross]),
        },
    }


def account_simulation(
    predictions: np.ndarray,
    actual: np.ndarray,
    dates: pd.DatetimeIndex,
    threshold: float = 0.01,
    initial_capital: float = 10_000.0,
    transaction_cost: float = TRANSACTION_COST,
) -> dict:
    """Causal single-position replay using only genuine BUY threshold crossings."""
    accepted = predictions >= threshold
    entries = accepted & ~np.r_[False, accepted[:-1]]
    capital = float(initial_capital)
    peak = capital
    maximum_drawdown = 0.0
    next_free_position = 0
    invested_days = 0
    trades: list[dict] = []

    for position in np.flatnonzero(entries):
        if position < next_free_position:
            continue
        net_return = float(actual[position]) - float(transaction_cost)
        capital_before = capital
        capital *= max(0.0, 1.0 + net_return)
        peak = max(peak, capital)
        maximum_drawdown = max(maximum_drawdown, (peak - capital) / peak if peak else 0.0)
        exit_position = min(position + HORIZON, len(dates) - 1)
        invested_days += max(0, exit_position - position)
        next_free_position = position + HORIZON
        trades.append(
            {
                "entry_date": str(pd.Timestamp(dates[position]).date()),
                "exit_date": str(pd.Timestamp(dates[exit_position]).date()),
                "predicted_return_percent": round(float(predictions[position]) * 100, 3),
                "net_return_percent": round(net_return * 100, 3),
                "capital_before": round(capital_before, 2),
                "capital_after": round(capital, 2),
            }
        )

    net_returns = [float(item["net_return_percent"]) / 100 for item in trades]
    span_days = max(1, len(dates) - 1)
    return {
        "initial_capital": round(initial_capital, 2),
        "final_capital": round(capital, 2),
        "profit": round(capital - initial_capital, 2),
        "return_percent": round((capital / initial_capital - 1.0) * 100, 3),
        "maximum_closed_trade_drawdown_percent": round(maximum_drawdown * 100, 3),
        "capital_utilization_percent": round(min(1.0, invested_days / span_days) * 100, 2),
        **trade_metrics(net_returns),
        "trades_detail": trades,
    }


def action_scores(predictions: np.ndarray, actual: np.ndarray) -> np.ndarray:
    """Point-in-time gross action score; outcomes enter evidence only after 20T."""
    scores: list[float] = []
    matured: list[float] = []
    direction: list[bool] = []
    equity = peak = 1.0
    maximum_drawdown = 0.0
    yearly_returns: dict[int, float] = {}
    for position, prediction in enumerate(predictions):
        evidence_position = position - HORIZON
        if evidence_position >= 0:
            realized = float(actual[evidence_position])
            matured.append(realized)
            direction.append(bool((predictions[evidence_position] >= 0) == (realized >= 0)))
            # Non-overlapping drawdown proxy, matching the production intent.
            if evidence_position % HORIZON == 0:
                equity *= max(0.000001, 1.0 + realized)
                peak = max(peak, equity)
                maximum_drawdown = max(maximum_drawdown, (peak - equity) / peak * 100)
            yearly_returns[evidence_position // 252] = yearly_returns.get(evidence_position // 252, 0.0) + realized
        count = len(matured)
        gains = sum(value for value in matured if value > 0)
        losses = abs(sum(value for value in matured if value < 0))
        profit_factor = gains / losses if losses > 0 else (3.0 if gains > 0 else None)
        average_trade = float(np.mean(matured)) * 100 if matured else None
        hit_rate = float(np.mean(np.asarray(matured) > 0)) * 100 if matured else None
        confidence = float(np.mean(direction)) * 100 if direction else 0.0
        stability = (sum(value > 0 for value in yearly_returns.values()) / len(yearly_returns) * 100) if yearly_returns else None
        quality = count >= 10 and average_trade is not None and average_trade >= 0 and profit_factor is not None and profit_factor >= 1.05
        values = {
            "profit_factor": np.clip(((min(profit_factor, 3.0) - 0.5) / 2.0) * 100, 0, 100) if profit_factor is not None else 0,
            "average_trade": np.clip(50 + average_trade * 12.5, 0, 100) if average_trade is not None else 0,
            "confidence": np.clip(confidence, 0, 100),
            # Gross prediction: trading costs deliberately remain downstream.
            "expected_return": np.clip(50 + float(prediction) * 100 * 5, 0, 100),
            "drawdown": np.clip(100 - maximum_drawdown * 2, 0, 100) if matured else 0,
            "hit_rate": np.clip(hit_rate, 0, 100) if hit_rate is not None else 0,
            "stability": np.clip(stability, 0, 100) if stability is not None else 0,
            "quality": 100 if quality else 0,
        }
        score = (
            values["profit_factor"] * 20 + values["average_trade"] * 10 + values["confidence"] * 20
            + values["expected_return"] * 15 + values["drawdown"] * 15 + values["hit_rate"] * 10
            + values["stability"] * 5 + values["quality"] * 5
        ) / 100
        scores.append(float(min(score, 64.0) if not quality else score))
    return np.asarray(scores)


def score_filtered_metrics(predictions: np.ndarray, actual: np.ndarray, dates: pd.DatetimeIndex) -> dict:
    """Calibrate before the rolling three-year untouched validation window."""
    scores = action_scores(predictions, actual)
    latest_year = int(dates.year.max())
    validation_start_year = latest_year - VALIDATION_YEARS + 1
    calibration = dates.year < validation_start_year
    validation = dates.year >= validation_start_year
    candidates: list[dict] = []
    for threshold in range(35, 81):
        accepted = (predictions >= 0.01) & (scores >= threshold)
        entries = accepted & ~np.r_[False, accepted[:-1]]
        values = actual[entries & calibration].tolist()
        stats = trade_metrics(values)
        if stats["trades"] >= 10:
            rank = float(stats["hit_rate"] or 0) + min(5.0, float(stats["profit_factor"] or 0)) * 10 + max(-5, min(5, float(stats["average_return_percent"] or 0))) * 3
            candidates.append({"threshold": threshold, "rank": rank, "stats": stats})
    if not candidates:
        return {"error": "insufficient_2023_calibration_entries"}
    eligible = [item for item in candidates if float(item["stats"]["profit_factor"] or 0) >= 1.5 and float(item["stats"]["average_return_percent"] or 0) > 0]
    best = max(eligible or candidates, key=lambda item: item["rank"])
    accepted = (predictions >= 0.01) & (scores >= best["threshold"])
    entries = accepted & ~np.r_[False, accepted[:-1]]
    gross = actual[entries & validation].tolist()
    return {
        "threshold": best["threshold"],
        "minimum_ai_score": best["threshold"] / 10,
        "calibration_end_year": validation_start_year - 1,
        "calibration": best["stats"],
        "validation_years": list(range(validation_start_year, latest_year + 1)),
        "gross": trade_metrics(gross),
        "net_cost_scenarios": {
            "0.10_percent": trade_metrics([value - 0.001 for value in gross]),
            "0.25_percent": trade_metrics([value - 0.0025 for value in gross]),
            "0.50_percent": trade_metrics([value - 0.005 for value in gross]),
        },
    }


def stable_quantile_score_filter(predictions: np.ndarray, actual: np.ndarray, dates: pd.DatetimeIndex) -> dict:
    """Causal rolling score quantile with yearly calibration coverage."""
    scores = action_scores(predictions, actual)
    latest_year = int(dates.year.max())
    validation_start_year = latest_year - VALIDATION_YEARS + 1
    calibration = dates.year < validation_start_year
    validation = dates.year >= validation_start_year
    calibration_years = sorted(set(int(year) for year in dates.year[calibration]))
    score_series = pd.Series(scores, index=dates)
    candidates: list[dict] = []
    for quantile in np.arange(0.40, 0.91, 0.05):
        # Threshold uses only the preceding two trading years; no future outcome.
        threshold = score_series.shift(1).rolling(504, min_periods=126).quantile(float(quantile)).to_numpy()
        accepted = (predictions >= 0.01) & (scores >= threshold) & np.isfinite(threshold)
        entries = accepted & ~np.r_[False, accepted[:-1]]
        values = actual[entries & calibration].tolist()
        stats = trade_metrics(values)
        yearly_entries = {year: int(np.sum(entries & (dates.year == year))) for year in calibration_years}
        years_with_coverage = sum(count >= 2 for count in yearly_entries.values())
        required_years = max(2, math.ceil(len(calibration_years) * 0.75))
        covered = years_with_coverage >= required_years
        if stats["trades"] >= 10 and covered:
            candidates.append({
                "quantile": round(float(quantile), 2), "threshold": threshold,
                "stats": stats, "yearly_entries": yearly_entries,
                "years_with_coverage": years_with_coverage, "required_years": required_years,
            })
    profitable = [item for item in candidates if float(item["stats"]["profit_factor"] or 0) >= 1.5 and float(item["stats"]["average_return_percent"] or 0) > 0]
    # Lowest qualifying quantile deliberately favours coverage over fitted peak performance.
    chosen = min(profitable, key=lambda item: item["quantile"]) if profitable else None
    if chosen is None:
        return {
            "status": "no_stable_profitable_threshold",
            "tested_candidates_with_yearly_coverage": len(candidates),
            "calibration_years": calibration_years,
            "validation_years": list(range(validation_start_year, latest_year + 1)),
        }
    threshold = chosen.pop("threshold")
    accepted = (predictions >= 0.01) & (scores >= threshold) & np.isfinite(threshold)
    entries = accepted & ~np.r_[False, accepted[:-1]]
    gross = actual[entries & validation].tolist()
    gross_stats = trade_metrics(gross)
    forward_passed = (
        gross_stats["trades"] >= 10
        and float(gross_stats["profit_factor"] or 0) >= 1.5
        and float(gross_stats["average_return_percent"] or 0) > 0
        and float(gross_stats["hit_rate"] or 0) >= 60
    )
    return {
        "status": "validated" if forward_passed else ("forward_failed" if len(gross) >= 10 else "insufficient_forward_events"),
        "rolling_window_trading_days": 504,
        "minimum_history_days": 126,
        "score_quantile": chosen["quantile"],
        "calibration_end_year": validation_start_year - 1,
        "calibration": chosen["stats"],
        "calibration_entries_by_year": chosen["yearly_entries"],
        "years_with_coverage": chosen["years_with_coverage"],
        "required_years_with_coverage": chosen["required_years"],
        "validation_years": list(range(validation_start_year, latest_year + 1)),
        "gross": gross_stats,
        "net_cost_scenarios": {
            "0.10_percent": trade_metrics([value - 0.001 for value in gross]),
            "0.25_percent": trade_metrics([value - 0.0025 for value in gross]),
            "0.50_percent": trade_metrics([value - 0.005 for value in gross]),
        },
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", required=True)
    parser.add_argument("--years", type=int, default=30)
    parser.add_argument(
        "--variants",
        default="",
        help="Comma-separated variant names; empty runs every variant.",
    )
    parser.add_argument("--output", type=Path)
    args = parser.parse_args()

    with Database() as database:
        instrument = InstrumentRepository(database).find_by_symbol(args.symbol)
        if instrument is None:
            raise SystemExit(f"Instrument nicht gefunden: {args.symbol}")
        benchmark_symbol = resolve_benchmark_symbol(instrument, "auto")
        bars = list(_import(database, instrument.symbol, "1d", args.years, persist=False).bars)
        benchmark_bars = list(_import(database, benchmark_symbol, "1d", args.years, persist=False).bars)
        index_row = database.fetch_one(
            """SELECT mi.id FROM index_memberships im JOIN market_indices mi ON mi.id=im.market_index_id
                WHERE im.instrument_id=%s AND im.removed_at IS NULL AND mi.symbol=%s LIMIT 1""",
            (int(instrument.id), benchmark_symbol),
        )
        if not index_row:
            index_row = database.fetch_one(
                "SELECT id FROM market_indices WHERE UPPER(symbol)=UPPER(%s) LIMIT 1",
                (benchmark_symbol,),
            )
        index_key = str(index_row["id"]) if index_row else ""
        index_context = context_series(database, "index60", index_key)
        sector_context = context_series(database, "sector60", str(instrument.sector or ""))

    raw = pd.DataFrame(
        {
            "open": [float(bar.open) for bar in bars],
            "high": [float(bar.high) for bar in bars],
            "low": [float(bar.low) for bar in bars],
            "close": [float(bar.close) for bar in bars],
            "volume": [float(bar.volume or 0) for bar in bars],
        },
        index=pd.DatetimeIndex([bar.timestamp.replace(tzinfo=None) for bar in bars]),
    )
    built = FeatureBuilder().build(
        timestamps=[bar.timestamp for bar in bars],
        open=raw["open"].tolist(), high=raw["high"].tolist(), low=raw["low"].tolist(),
        close=raw["close"].tolist(), volume=raw["volume"].tolist(),
        profile=triple_daily_macro_profile(),
        benchmark_close=aligned_benchmark(bars, benchmark_bars),
    )
    feature_index = pd.DatetimeIndex([value.replace(tzinfo=None) for value in built.timestamps])
    latest_data_year = int(feature_index.year.max())
    oos_years = tuple(range(latest_data_year - OOS_LOOKBACK_YEARS + 1, latest_data_year + 1))
    base = pd.DataFrame(built.columns, index=feature_index).astype(float)
    ts = timeseries_features(raw).reindex(feature_index)
    arima = arima_features(raw).reindex(feature_index)
    index_values, index_available = align_context(feature_index, index_context)
    sector_values, sector_available = align_context(feature_index, sector_context)
    pytorch = pd.DataFrame(
        {
            "PYTORCH__INDEX60": index_values,
            "PYTORCH__SECTOR60": sector_values,
            "PYTORCH__INDEX60_AVAILABLE": index_available,
            "PYTORCH__SECTOR60_AVAILABLE": sector_available,
            "PYTORCH__CONTEXT_MIN": np.minimum(index_values, sector_values),
            "PYTORCH__CONTEXT_SPREAD": sector_values - index_values,
        },
        index=feature_index,
    )
    close = raw["close"].reindex(feature_index)
    target = close.shift(-HORIZON) / close - 1.0
    variants = {
        "A_baseline": base,
        "B_timeseries": pd.concat([base, ts], axis=1),
        "C_timeseries_pytorch": pd.concat([base, ts, pytorch], axis=1),
        "D_arima_timeseries": pd.concat([base, ts, arima], axis=1),
        "E_arima_timeseries_pytorch": pd.concat([base, ts, arima, pytorch], axis=1),
        "F_pure_arima": arima,
        "G_pure_arima_timeseries": pd.concat([arima, ts], axis=1),
    }
    requested_variants = {value.strip() for value in args.variants.split(",") if value.strip()}
    if requested_variants:
        unknown = requested_variants.difference(variants)
        if unknown:
            parser.error(f"unknown variants: {', '.join(sorted(unknown))}")
        variants = {name: frame for name, frame in variants.items() if name in requested_variants}
    predictions: dict[str, list[np.ndarray]] = {name: [] for name in variants}
    truths: dict[str, list[np.ndarray]] = {name: [] for name in variants}
    dates: dict[str, list[pd.DatetimeIndex]] = {name: [] for name in variants}
    folds: dict[str, list[dict]] = {name: [] for name in variants}

    for year in oos_years:
        test_mask = feature_index.year == year
        train_mask = feature_index.year < year
        for name, frame in variants.items():
            complete = frame.notna().all(axis=1) & target.notna()
            train_positions = np.where(train_mask & complete)[0]
            test_positions = np.where(test_mask & complete)[0]
            if name in {"F_pure_arima", "G_pure_arima_timeseries"}:
                if len(test_positions) < 30:
                    continue
                if name == "F_pure_arima":
                    predicted = arima.iloc[test_positions]["ARIMA__FORECAST_RETURN_20"].to_numpy(dtype=float)
                else:
                    direct_forecasts = pd.DataFrame(
                        {
                            "arima": np.log1p(
                                arima.iloc[test_positions]["ARIMA__FORECAST_RETURN_20"].clip(lower=-0.95)
                            ),
                            "trend20": ts.iloc[test_positions]["TS__TREND_FORECAST_20_20"],
                            "trend60": ts.iloc[test_positions]["TS__TREND_FORECAST_20_60"],
                            "trend120": ts.iloc[test_positions]["TS__TREND_FORECAST_20_120"],
                            "ar60": ts.iloc[test_positions]["TS__AR_FORECAST_20_60"],
                        }
                    )
                    predicted = np.expm1(direct_forecasts.median(axis=1).to_numpy(dtype=float))
                y_test = target.iloc[test_positions].to_numpy(dtype=float)
                predictions[name].append(predicted)
                truths[name].append(y_test)
                dates[name].append(feature_index[test_positions])
                folds[name].append({"year": year, **metrics(predicted, y_test)})
                continue
            if len(train_positions) < 1000 or len(test_positions) < 30:
                continue
            purge_boundary = test_positions[0] - HORIZON
            train_positions = train_positions[train_positions < purge_boundary]
            x_train = frame.iloc[train_positions].to_numpy(dtype=float)
            y_train = target.iloc[train_positions].to_numpy(dtype=float)
            x_test = frame.iloc[test_positions].to_numpy(dtype=float)
            y_test = target.iloc[test_positions].to_numpy(dtype=float)
            age_days = (feature_index[train_positions[-1]] - feature_index[train_positions]).days.to_numpy()
            sample_weight = np.power(0.5, age_days / (365.25 * 4.0))
            scaler = StandardScaler().fit(x_train)
            model = HistGradientBoostingRegressor(max_iter=160, learning_rate=0.05, max_leaf_nodes=15, l2_regularization=1.0, random_state=42 + year)
            model.fit(scaler.transform(x_train), y_train, sample_weight=sample_weight)
            predicted = model.predict(scaler.transform(x_test))
            predictions[name].append(predicted)
            truths[name].append(y_test)
            dates[name].append(feature_index[test_positions])
            folds[name].append({"year": year, **metrics(predicted, y_test)})

    result = {
        "symbol": instrument.symbol,
        "benchmark": benchmark_symbol,
        "sector": instrument.sector,
        "horizon_days": HORIZON,
        "method": "expanding-yearly-walk-forward-purged-recency-weighted",
        "oos_years": list(oos_years),
        "transaction_cost": TRANSACTION_COST,
        "pytorch_context": {"index_key": index_key, "index_points": len(index_context), "sector_points": len(sector_context)},
        "feature_counts": {name: frame.shape[1] for name, frame in variants.items()},
        "variants": {},
    }
    for name in variants:
        if not predictions[name]:
            result["variants"][name] = {"error": "no_valid_folds"}
            continue
        result["variants"][name] = {
            "overall": metrics(np.concatenate(predictions[name]), np.concatenate(truths[name])),
            "account_simulation": account_simulation(
                np.concatenate(predictions[name]), np.concatenate(truths[name]),
                pd.DatetimeIndex(np.concatenate([value.to_numpy() for value in dates[name]])),
            ),
            "ki_score_filter": score_filtered_metrics(
                np.concatenate(predictions[name]), np.concatenate(truths[name]),
                pd.DatetimeIndex(np.concatenate([value.to_numpy() for value in dates[name]])),
            ),
            "ki_score_filter_stable_quantile": stable_quantile_score_filter(
                np.concatenate(predictions[name]), np.concatenate(truths[name]),
                pd.DatetimeIndex(np.concatenate([value.to_numpy() for value in dates[name]])),
            ),
            "folds": folds[name],
        }
    rendered = json.dumps(result, indent=2, ensure_ascii=False)
    print(rendered)
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(rendered + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
