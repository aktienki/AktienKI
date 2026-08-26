from __future__ import annotations

import argparse
from datetime import datetime, timezone
import json
from math import prod
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.ensemble import ExtraTreesRegressor, HistGradientBoostingRegressor, RandomForestRegressor

from app.cli.main import _import
from app.database.connection import Database


HORIZONS = (5, 10, 15, 20)
PHASES = ("bull", "sideways", "stress")


def bars_frame(bars, prefix: str) -> pd.DataFrame:
    rows = [{"date": b.timestamp.replace(tzinfo=None), "open": float(b.open), "high": float(b.high),
             "low": float(b.low), "close": float(b.close), "volume": float(b.volume or 0)} for b in bars]
    data = pd.DataFrame(rows).drop_duplicates("date").sort_values("date").set_index("date")
    close, high, low = data.close, data.high, data.low
    output = pd.DataFrame(index=data.index)
    for days in (1, 5, 10, 20, 60):
        output[f"{prefix}_return_{days}"] = close.pct_change(days)
    output[f"{prefix}_volatility_10"] = close.pct_change().rolling(10).std() * np.sqrt(252)
    output[f"{prefix}_volatility_20"] = close.pct_change().rolling(20).std() * np.sqrt(252)
    ema12, ema20, ema26 = close.ewm(span=12, adjust=False).mean(), close.ewm(span=20, adjust=False).mean(), close.ewm(span=26, adjust=False).mean()
    macd = ema12 - ema26
    output[f"{prefix}_macd"] = macd / close
    output[f"{prefix}_macd_hist"] = (macd - macd.ewm(span=9, adjust=False).mean()) / close
    output[f"{prefix}_ema20_slope_10"] = ema20.pct_change(10)
    delta = close.diff()
    gain = delta.clip(lower=0).ewm(alpha=1 / 14, adjust=False).mean()
    loss = (-delta.clip(upper=0)).ewm(alpha=1 / 14, adjust=False).mean()
    output[f"{prefix}_rsi_14"] = (100 - 100 / (1 + gain / loss.replace(0, np.nan))) / 100
    for days in (20, 50, 200):
        output[f"{prefix}_sma_{days}_distance"] = close / close.rolling(days).mean() - 1
    true_range = pd.concat(((high - low), (high - close.shift()).abs(), (low - close.shift()).abs()), axis=1).max(axis=1)
    atr = true_range.ewm(alpha=1 / 14, adjust=False).mean()
    output[f"{prefix}_atr_14"] = atr / close
    volume_mean, volume_std = data.volume.rolling(20).mean(), data.volume.rolling(20).std()
    output[f"{prefix}_volume_z20"] = (data.volume - volume_mean) / volume_std.replace(0, np.nan)
    up, down = high.diff(), -low.diff()
    plus_dm = pd.Series(np.where((up > down) & (up > 0), up, 0.0), index=data.index)
    minus_dm = pd.Series(np.where((down > up) & (down > 0), down, 0.0), index=data.index)
    plus_di = 100 * plus_dm.ewm(alpha=1 / 14, adjust=False).mean() / atr
    minus_di = 100 * minus_dm.ewm(alpha=1 / 14, adjust=False).mean() / atr
    output[f"{prefix}_di_direction"] = (plus_di - minus_di) / 100
    output.attrs["close"] = close
    output.attrs["phase"] = pd.Series(np.where(
        (close > close.rolling(50).mean()) & (close.rolling(50).mean() > close.rolling(200).mean())
        & (ema20.pct_change(10) > 0) & (plus_di > minus_di), "bull",
        np.where((close < close.rolling(50).mean()) & (close.rolling(50).mean() < close.rolling(200).mean())
                 & (ema20.pct_change(10) < 0) & (minus_di > plus_di), "stress", "sideways")
    ), index=data.index)
    return output


def estimator_set(seed: int):
    return [
        HistGradientBoostingRegressor(max_iter=180, learning_rate=.045, max_leaf_nodes=20, l2_regularization=.5, random_state=seed),
        ExtraTreesRegressor(n_estimators=140, min_samples_leaf=8, max_features=.75, n_jobs=-1, random_state=seed + 1),
        RandomForestRegressor(n_estimators=140, min_samples_leaf=8, max_features=.75, n_jobs=-1, random_state=seed + 2),
    ]


def fit_predict(train_X, train_y, test_X, seed: int) -> np.ndarray:
    lower, upper = np.quantile(train_y, [.01, .99])
    target = np.clip(train_y, lower, upper)
    predictions = []
    for model in estimator_set(seed):
        model.fit(train_X, target)
        predictions.append(model.predict(test_X))
    return np.mean(predictions, axis=0)


def metrics(actual: np.ndarray, predicted: np.ndarray, threshold: float = .01) -> dict:
    direction = float(((predicted >= 0) == (actual >= 0)).mean())
    selected = actual[predicted >= threshold] - .005
    wins, losses = selected[selected > 0], selected[selected < 0]
    return {
        "samples": len(actual), "direction_accuracy": direction,
        "mae": float(np.abs(actual - predicted).mean()), "trades": len(selected),
        "win_rate": float((selected > 0).mean()) if len(selected) else None,
        "profit_factor": float(wins.sum() / abs(losses.sum())) if len(losses) and losses.sum() else None,
        "total_return": float(prod(1 + selected) - 1) if len(selected) else 0.0,
        "mean_return": float(selected.mean()) if len(selected) else None,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", default="GOOGL")
    parser.add_argument("--benchmark", default="^GSPC")
    parser.add_argument("--years", type=int, default=10)
    parser.add_argument("--minimum-phase-samples", type=int, default=150)
    parser.add_argument("--output", type=Path, default=Path("reports/googl-regime-ensemble-experts.json"))
    args = parser.parse_args()
    with Database() as database:
        stock_bars = list(_import(database, args.symbol, "1d", args.years, persist=False).bars)
        market_bars = list(_import(database, args.benchmark, "1d", args.years, persist=False).bars)
    stock, market = bars_frame(stock_bars, "stock"), bars_frame(market_bars, "market")
    stock_close, phase = stock.attrs["close"], market.attrs["phase"]
    stock.attrs.clear()
    market.attrs.clear()
    data = stock.join(market, how="inner").replace([np.inf, -np.inf], np.nan)
    data["phase"] = phase.reindex(data.index)
    feature_names = [name for name in data.columns if name != "phase"]
    report = {"symbol": args.symbol, "benchmark": args.benchmark, "generated_at": datetime.now(timezone.utc).isoformat(),
              "method": "causal_three_phase_classical_tree_ensemble_experts", "feature_names": feature_names,
              "phase_definition": "benchmark MA50/MA200 + EMA20 slope10 + DI direction", "folds": []}
    for horizon in HORIZONS:
        scoped = data.copy()
        scoped["target"] = stock_close.shift(-horizon) / stock_close - 1
        scoped = scoped.dropna(subset=feature_names + ["phase", "target"])
        for year in range(2022, 2027):
            test_start = pd.Timestamp(f"{year}-01-01")
            purge_cutoff = test_start - pd.offsets.BDay(horizon)
            train, test = scoped[scoped.index < purge_cutoff], scoped[scoped.index.year == year]
            if len(train) < 500 or len(test) < 20:
                continue
            global_prediction = fit_predict(train[feature_names], train.target.to_numpy(), test[feature_names], 1000 + year + horizon)
            routed_prediction = np.empty(len(test), dtype=float)
            fallback = []
            phase_counts = {}
            for current_phase in PHASES:
                test_mask = test.phase == current_phase
                phase_train = train[train.phase == current_phase]
                phase_counts[current_phase] = len(phase_train)
                if not test_mask.any():
                    continue
                if len(phase_train) < args.minimum_phase_samples:
                    routed_prediction[test_mask.to_numpy()] = global_prediction[test_mask.to_numpy()]
                    fallback.append(current_phase)
                else:
                    routed_prediction[test_mask.to_numpy()] = fit_predict(
                        phase_train[feature_names], phase_train.target.to_numpy(), test.loc[test_mask, feature_names],
                        2000 + year + horizon + PHASES.index(current_phase),
                    )
            report["folds"].append({"horizon": horizon, "test_year": year, "train_samples": len(train),
                                    "test_samples": len(test), "phase_train_samples": phase_counts,
                                    "fallback_phases": fallback, "global": metrics(test.target.to_numpy(), global_prediction),
                                    "regime_experts": metrics(test.target.to_numpy(), routed_prediction)})
            print(f"FOLD horizon={horizon} year={year} test={len(test)} fallback={fallback}", flush=True)
    summary = {}
    for horizon in HORIZONS:
        folds = [fold for fold in report["folds"] if fold["horizon"] == horizon]
        summary[str(horizon)] = {}
        for model in ("global", "regime_experts"):
            weights = np.asarray([fold[model]["samples"] for fold in folds], dtype=float)
            summary[str(horizon)][model] = {
                key: float(np.average([fold[model][key] for fold in folds], weights=weights))
                for key in ("direction_accuracy", "mae")
            }
            trades = sum(fold[model]["trades"] for fold in folds)
            summary[str(horizon)][model]["trades"] = trades
            valid_pf = [fold[model]["profit_factor"] for fold in folds if fold[model]["profit_factor"] is not None]
            summary[str(horizon)][model]["mean_fold_profit_factor"] = float(np.mean(valid_pf)) if valid_pf else None
        summary[str(horizon)]["direction_accuracy_delta"] = (
            summary[str(horizon)]["regime_experts"]["direction_accuracy"] - summary[str(horizon)]["global"]["direction_accuracy"]
        )
    report["summary"] = summary
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    print(json.dumps(summary, indent=2)); print(f"REPORT {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
