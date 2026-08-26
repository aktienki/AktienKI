from __future__ import annotations

import argparse
import json
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable

import joblib
import numpy as np
import pandas as pd
from sklearn.base import clone
from sklearn.ensemble import ExtraTreesRegressor, HistGradientBoostingRegressor
from sklearn.linear_model import Ridge
from sklearn.metrics import mean_absolute_error, mean_squared_error
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler

from app.cli.main import _import
from app.config.settings import settings
from app.database.connection import Database
from app.repositories.instrument_repository import InstrumentRepository
from app.repositories.market_data_repository import MarketDataRepository


HORIZONS = (5, 10, 15, 20)
MACROS = ("^STOXX", "^STOXX50E", "^GSPC", "^IXIC", "VDAX", "AGG", "US2Y")


@dataclass(frozen=True)
class Evaluation:
    horizon: int
    variant: str
    samples: int
    trades: int
    direction_accuracy: float
    mae: float
    rmse: float
    zero_return_rmse: float
    rmse_skill: float
    profit_factor: float
    maximum_drawdown: float
    total_return: float
    yearly_direction_accuracy: dict[str, float]
    passed: bool
    blockers: tuple[str, ...]


def _daily_close(bars: Iterable) -> pd.Series:
    values: dict[pd.Timestamp, float] = {}
    for bar in bars:
        stamp = pd.Timestamp(bar.timestamp)
        if stamp.tzinfo is None:
            stamp = stamp.tz_localize("UTC")
        day = stamp.tz_convert("Europe/Berlin").normalize().tz_localize(None)
        close = float(bar.adjusted_close or bar.close)
        if np.isfinite(close) and close > 0:
            values[day] = close
    if not values:
        return pd.Series(index=pd.DatetimeIndex([]), dtype=float)
    return pd.Series(values, dtype=float).sort_index()


def _load_history(database: Database, symbol: str, years: int) -> pd.Series:
    imported = _daily_close(
        _import(database, symbol, "1d", years, persist=False).bars
    )
    if not imported.empty:
        return imported
    instrument = InstrumentRepository(database).find_by_symbol(symbol)
    if instrument is None or instrument.id is None:
        return imported
    stored = MarketDataRepository(database).load_history(
        instrument.id, "1d", ascending=True
    )
    fallback = _daily_close(stored)
    if fallback.empty:
        raise RuntimeError(f"Keine Historie für {symbol}")
    print(f"Fallback {symbol}: stored={len(fallback)}")
    return fallback


def _returns(series: pd.Series, periods: int) -> pd.Series:
    return series.pct_change(periods, fill_method=None)


def _rsi(series: pd.Series, period: int = 14) -> pd.Series:
    delta = series.diff()
    gain = delta.clip(lower=0).rolling(period).mean()
    loss = -delta.clip(upper=0).rolling(period).mean()
    ratio = gain / loss.replace(0, np.nan)
    return 100 - 100 / (1 + ratio)


def _base_features(close: pd.Series) -> pd.DataFrame:
    frame = pd.DataFrame(index=close.index)
    daily = _returns(close, 1)
    for period in (1, 2, 5, 10, 20, 60, 120):
        frame[f"dax_return_{period}"] = _returns(close, period)
    for period in (5, 10, 20, 60):
        frame[f"dax_volatility_{period}"] = daily.rolling(period).std() * np.sqrt(252)
    for period in (8, 20, 50, 100, 200):
        average = close.rolling(period).mean()
        frame[f"dax_sma_ratio_{period}"] = close / average - 1
    frame["dax_rsi_7"] = _rsi(close, 7) / 100
    frame["dax_rsi_14"] = _rsi(close, 14) / 100
    frame["dax_drawdown_252"] = close / close.rolling(252).max() - 1
    frame["dax_distance_high_20"] = close / close.rolling(20).max() - 1
    frame["dax_distance_low_20"] = close / close.rolling(20).min() - 1
    return frame


def _breadth_features(calendar: pd.DatetimeIndex, histories: dict[str, pd.Series]) -> pd.DataFrame:
    prices = pd.DataFrame({symbol: values.reindex(calendar) for symbol, values in histories.items()})
    # No forward fill: only information from an actual member candle is used.
    one_day = prices.pct_change(fill_method=None)
    result = pd.DataFrame(index=calendar)
    result["breadth_positive_1"] = (one_day > 0).sum(axis=1) / one_day.notna().sum(axis=1).replace(0, np.nan)
    result["breadth_mean_return_1"] = one_day.mean(axis=1)
    result["breadth_median_return_1"] = one_day.median(axis=1)
    result["breadth_dispersion_1"] = one_day.std(axis=1)
    result["breadth_advance_decline"] = ((one_day > 0).sum(axis=1) - (one_day < 0).sum(axis=1)) / one_day.notna().sum(axis=1).replace(0, np.nan)
    result["breadth_coverage"] = prices.notna().sum(axis=1) / max(1, prices.shape[1])
    for period in (5, 20, 60):
        member_return = prices.pct_change(period, fill_method=None)
        result[f"breadth_positive_{period}"] = (member_return > 0).sum(axis=1) / member_return.notna().sum(axis=1).replace(0, np.nan)
        result[f"breadth_mean_return_{period}"] = member_return.mean(axis=1)
        result[f"breadth_dispersion_{period}"] = member_return.std(axis=1)
    for period in (20, 50, 200):
        sma = prices.rolling(period).mean()
        valid = prices.notna() & sma.notna()
        result[f"breadth_above_sma_{period}"] = ((prices > sma) & valid).sum(axis=1) / valid.sum(axis=1).replace(0, np.nan)
    result["breadth_thrust_5"] = result["breadth_positive_1"].rolling(5).mean()
    result["breadth_thrust_20"] = result["breadth_positive_1"].rolling(20).mean()
    return result


def _macro_features(calendar: pd.DatetimeIndex, histories: dict[str, pd.Series]) -> pd.DataFrame:
    result = pd.DataFrame(index=calendar)
    for symbol, raw in histories.items():
        label = symbol.replace("^", "").replace("/", "_")
        aligned = raw.reindex(calendar, method="ffill", tolerance=pd.Timedelta("5D"))
        if symbol in {"VDAX", "US2Y"}:
            result[f"macro_{label}_level"] = aligned
        for period in (1, 5, 20, 60):
            result[f"macro_{label}_return_{period}"] = _returns(aligned, period)
    return result


def _models(seed: int) -> dict[str, object]:
    return {
        "ridge": make_pipeline(StandardScaler(), Ridge(alpha=8.0)),
        "hist_gradient": HistGradientBoostingRegressor(
            learning_rate=0.035, max_iter=220, max_leaf_nodes=15,
            l2_regularization=2.0, random_state=seed,
        ),
        "extra_trees": ExtraTreesRegressor(
            n_estimators=180, max_depth=8, min_samples_leaf=8,
            max_features=0.7, n_jobs=-1, random_state=seed,
        ),
    }


def _walk_forward(
    features: pd.DataFrame,
    close: pd.Series,
    *,
    horizon: int,
    first_test_year: int,
    seed: int,
) -> tuple[pd.DataFrame, dict[str, object]]:
    target = close.shift(-horizon) / close - 1
    data = features.copy()
    data["target"] = target
    data = data.replace([np.inf, -np.inf], np.nan).dropna()
    feature_names = [column for column in data.columns if column != "target"]
    predictions: list[pd.DataFrame] = []
    for month in pd.period_range(f"{first_test_year}-01", data.index.max(), freq="M"):
        test_mask = data.index.to_period("M") == month
        test_positions = np.flatnonzero(test_mask)
        if not len(test_positions):
            continue
        train_limit = max(0, int(test_positions[0]) - horizon)
        train = data.iloc[:train_limit]
        test = data.loc[test_mask]
        if len(train) < 1000 or len(test) == 0:
            continue
        # Winsorisation is fitted on training data only.
        lower = train[feature_names].quantile(0.005)
        upper = train[feature_names].quantile(0.995)
        train_x = train[feature_names].clip(lower=lower, upper=upper, axis=1)
        test_x = test[feature_names].clip(lower=lower, upper=upper, axis=1)
        y_train = train["target"].clip(train["target"].quantile(0.01), train["target"].quantile(0.99))
        values: dict[str, np.ndarray] = {}
        fitted = {}
        for name, template in _models(seed).items():
            model = clone(template)
            model.fit(train_x, y_train)
            values[name] = np.asarray(model.predict(test_x), dtype=float)
            fitted[name] = model
        fold = pd.DataFrame(index=test.index)
        fold["truth"] = test["target"]
        for name, value in values.items():
            fold[name] = value
        fold["ensemble"] = np.median(np.column_stack(list(values.values())), axis=1)
        predictions.append(fold)
    if not predictions:
        raise RuntimeError(f"Kein Walk-Forward-Fold für Horizont {horizon}")
    combined = pd.concat(predictions).sort_index()
    source_prices = close.reindex(combined.index)
    calendar_positions = {stamp: index for index, stamp in enumerate(close.index)}
    combined["source_price"] = source_prices
    combined["predicted_price"] = source_prices * (1 + combined["ensemble"])
    combined["actual_price"] = source_prices * (1 + combined["truth"])
    combined["target_date"] = [
        close.index[calendar_positions[stamp] + horizon]
        if stamp in calendar_positions and calendar_positions[stamp] + horizon < len(close.index)
        else pd.NaT
        for stamp in combined.index
    ]
    # Final refit is strictly separate from frozen walk-forward metrics.
    final = data.iloc[:-horizon] if len(data) > horizon else data
    lower = final[feature_names].quantile(0.005)
    upper = final[feature_names].quantile(0.995)
    final_x = final[feature_names].clip(lower=lower, upper=upper, axis=1)
    final_y = final["target"].clip(final["target"].quantile(0.01), final["target"].quantile(0.99))
    fitted = {}
    for name, template in _models(seed).items():
        model = clone(template)
        model.fit(final_x, final_y)
        fitted[name] = model
    bundle = {"models": fitted, "feature_names": feature_names, "lower": lower, "upper": upper}
    return combined, bundle


def _evaluate(frame: pd.DataFrame, *, horizon: int, variant: str) -> Evaluation:
    truth = frame["truth"].to_numpy(float)
    predicted = frame["ensemble"].to_numpy(float)
    threshold = max(0.003, 0.0075 * np.sqrt(horizon / 20))
    trade_returns: list[float] = []
    equity = 1.0
    peak = 1.0
    maximum_drawdown = 0.0
    next_entry = 0
    for index, (actual, estimate) in enumerate(zip(truth, predicted, strict=True)):
        if index < next_entry or estimate < threshold:
            continue
        value = max(-0.999, actual - 0.002)
        trade_returns.append(value)
        equity *= 1 + value
        peak = max(peak, equity)
        maximum_drawdown = max(maximum_drawdown, 1 - equity / peak)
        next_entry = index + horizon
    gains = sum(value for value in trade_returns if value > 0)
    losses = -sum(value for value in trade_returns if value < 0)
    profit_factor = gains / losses if losses > 0 else (999.0 if gains > 0 else 0.0)
    direction = float(np.mean(np.sign(predicted) == np.sign(truth)))
    rmse = float(mean_squared_error(truth, predicted) ** 0.5)
    zero_rmse = float(mean_squared_error(truth, np.zeros_like(truth)) ** 0.5)
    yearly = {
        str(year): float(np.mean(np.sign(group["ensemble"]) == np.sign(group["truth"])))
        for year, group in frame.groupby(frame.index.year)
    }
    blockers = []
    if direction < 0.53:
        blockers.append("direction_accuracy")
    if profit_factor < 1.25:
        blockers.append("profit_factor")
    if len(trade_returns) < 20:
        blockers.append("minimum_trades")
    if maximum_drawdown > 0.25:
        blockers.append("maximum_drawdown")
    if rmse >= zero_rmse:
        blockers.append("zero_return_baseline")
    if yearly and sum(value >= 0.5 for value in yearly.values()) < max(2, len(yearly) - 1):
        blockers.append("year_stability")
    return Evaluation(
        horizon=horizon, variant=variant, samples=len(frame), trades=len(trade_returns),
        direction_accuracy=direction, mae=float(mean_absolute_error(truth, predicted)),
        rmse=rmse, zero_return_rmse=zero_rmse, rmse_skill=1 - rmse / zero_rmse,
        profit_factor=float(profit_factor), maximum_drawdown=float(maximum_drawdown),
        total_return=float(np.prod([1 + value for value in trade_returns]) - 1 if trade_returns else 0),
        yearly_direction_accuracy=yearly, passed=not blockers, blockers=tuple(blockers),
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--years", type=int, default=12)
    parser.add_argument("--first-test-year", type=int, default=2019)
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--horizons", type=int, nargs="+", default=list(HORIZONS))
    parser.add_argument("--output", type=Path, default=Path("reports/dax_breadth_challenger.json"))
    parser.add_argument("--artifact-dir", type=Path, default=settings.model_path / "challengers" / "dax_breadth_v1")
    args = parser.parse_args()

    database = Database()
    members = database.fetch_all(
        """SELECT i.symbol FROM index_memberships im
           JOIN market_indices mi ON mi.id=im.market_index_id
           JOIN instruments i ON i.id=im.instrument_id
           WHERE UPPER(mi.symbol)=UPPER('^GDAXI') ORDER BY i.symbol"""
    )
    symbols = [row["symbol"] for row in members]
    dax = _load_history(database, "^GDAXI", args.years)
    histories = {
        symbol: _load_history(database, symbol, args.years)
        for symbol in symbols
    }
    macro_histories = {
        symbol: _load_history(database, symbol, args.years)
        for symbol in MACROS
    }
    calendar = dax.index
    technical = _base_features(dax)
    breadth = _breadth_features(calendar, histories)
    macros = _macro_features(calendar, macro_histories)
    full = pd.concat([technical, breadth, macros], axis=1)

    evaluations: list[Evaluation] = []
    bundles: dict[int, dict[str, object]] = {}
    comparison_points: dict[str, list[dict[str, object]]] = {}
    selected_horizons = tuple(dict.fromkeys(args.horizons))
    if any(horizon not in HORIZONS for horizon in selected_horizons):
        raise ValueError(f"Erlaubte Horizonte: {HORIZONS}")
    for horizon in selected_horizons:
        baseline_frame, _ = _walk_forward(
            technical, dax, horizon=horizon, first_test_year=args.first_test_year, seed=args.seed
        )
        evaluations.append(_evaluate(baseline_frame, horizon=horizon, variant="technical_only"))
        challenger_frame, bundle = _walk_forward(
            full, dax, horizon=horizon, first_test_year=args.first_test_year, seed=args.seed
        )
        result = _evaluate(challenger_frame, horizon=horizon, variant="dax_breadth_macro")
        evaluations.append(result)
        bundles[horizon] = bundle
        comparison_points[str(horizon)] = [
            {
                "source_date": stamp.date().isoformat(),
                "target_date": pd.Timestamp(row["target_date"]).date().isoformat(),
                "source_price": round(float(row["source_price"]), 4),
                "predicted_return": round(float(row["ensemble"]), 8),
                "actual_return": round(float(row["truth"]), 8),
                "predicted_price": round(float(row["predicted_price"]), 4),
                "actual_price": round(float(row["actual_price"]), 4),
            }
            for stamp, row in challenger_frame.dropna(
                subset=["target_date", "source_price", "predicted_price", "actual_price"]
            ).iterrows()
        ]
        print(
            f"DAX {horizon}T breadth: direction={result.direction_accuracy:.2%} "
            f"pf={result.profit_factor:.2f} trades={result.trades} "
            f"skill={result.rmse_skill:.2%} passed={result.passed}"
        )

    promoted = [item.horizon for item in evaluations if item.variant == "dax_breadth_macro" and item.passed]
    args.output.parent.mkdir(parents=True, exist_ok=True)
    report = {
        "created_at": datetime.now(timezone.utc).isoformat(),
        "symbol": "^GDAXI",
        "version": "dax-breadth-v1",
        "members": len(symbols),
        "member_symbols": symbols,
        "macro_symbols": list(MACROS),
        "horizons": list(selected_horizons),
        "first_test_year": args.first_test_year,
        "method": "monthly_expanding_walk_forward_with_horizon_purge",
        "quality_gate": {
            "minimum_direction_accuracy": 0.53,
            "minimum_profit_factor": 1.25,
            "minimum_trades": 20,
            "maximum_drawdown": 0.25,
            "must_beat_zero_return_rmse": True,
        },
        "evaluations": [asdict(item) for item in evaluations],
        "comparison_points": comparison_points,
        "promoted_horizons": promoted,
    }
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False, allow_nan=False))
    if promoted:
        args.artifact_dir.mkdir(parents=True, exist_ok=True)
        for horizon in promoted:
            joblib.dump(bundles[horizon], args.artifact_dir / f"future_return_{horizon}.joblib")
        (args.artifact_dir / "manifest.json").write_text(
            json.dumps(report, indent=2, ensure_ascii=False, allow_nan=False)
        )
    print(f"Report: {args.output} promoted={promoted}")


if __name__ == "__main__":
    main()
