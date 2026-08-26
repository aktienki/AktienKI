from __future__ import annotations

import argparse
from datetime import datetime, timezone
import json
from pathlib import Path

import numpy as np
import pandas as pd

from app.cli.main import _import
from app.database.connection import Database


DEFAULT_SYMBOLS = ("^GDAXI", "^GSPC", "^N225")
HORIZONS = (20, 60)


def frame(bars) -> pd.DataFrame:
    rows = [{
        "date": bar.timestamp.replace(tzinfo=None), "open": float(bar.open),
        "high": float(bar.high), "low": float(bar.low), "close": float(bar.close),
    } for bar in bars if float(bar.close) > 0]
    data = pd.DataFrame(rows).drop_duplicates("date").sort_values("date").set_index("date")
    close, high, low = data.close, data.high, data.low
    data["return_20"] = close.pct_change(20)
    data["sma_20"], data["sma_50"], data["sma_200"] = (
        close.rolling(20).mean(), close.rolling(50).mean(), close.rolling(200).mean()
    )
    ema_12, ema_20, ema_26 = close.ewm(span=12, adjust=False).mean(), close.ewm(span=20, adjust=False).mean(), close.ewm(span=26, adjust=False).mean()
    macd = ema_12 - ema_26
    data["macd_hist"] = macd - macd.ewm(span=9, adjust=False).mean()
    data["ema20_slope_10"] = ema_20.pct_change(10)
    delta = close.diff()
    gain, loss = delta.clip(lower=0).ewm(alpha=1 / 14, adjust=False).mean(), (-delta.clip(upper=0)).ewm(alpha=1 / 14, adjust=False).mean()
    data["rsi_14"] = 100 - 100 / (1 + gain / loss.replace(0, np.nan))
    true_range = pd.concat(((high - low), (high - close.shift()).abs(), (low - close.shift()).abs()), axis=1).max(axis=1)
    atr = true_range.ewm(alpha=1 / 14, adjust=False).mean()
    up, down = high.diff(), -low.diff()
    plus_dm = pd.Series(np.where((up > down) & (up > 0), up, 0.0), index=data.index)
    minus_dm = pd.Series(np.where((down > up) & (down > 0), down, 0.0), index=data.index)
    plus_di = 100 * plus_dm.ewm(alpha=1 / 14, adjust=False).mean() / atr
    minus_di = 100 * minus_dm.ewm(alpha=1 / 14, adjust=False).mean() / atr
    data["adx_14"] = (100 * (plus_di - minus_di).abs() / (plus_di + minus_di).replace(0, np.nan)).ewm(alpha=1 / 14, adjust=False).mean()
    data["di_direction"] = np.sign(plus_di - minus_di)
    data["volatility_20"] = close.pct_change().rolling(20).std() * np.sqrt(252)
    for horizon in HORIZONS:
        data[f"future_{horizon}"] = close.shift(-horizon) / close - 1
    return data


def phases(data: pd.DataFrame) -> dict[str, pd.Series]:
    close = data.close
    votes = (
        np.sign(data.return_20.fillna(0))
        + np.sign(data.macd_hist.fillna(0))
        + np.sign(data.rsi_14.fillna(50) - 50)
        + np.sign(data.ema20_slope_10.fillna(0))
        + np.sign((close / data.sma_200 - 1).fillna(0))
    )
    expected_20_vol = data.volatility_20 * np.sqrt(20 / 252)
    adaptive = np.maximum(0.015, 0.35 * expected_20_vol)
    ma_bull = (close > data.sma_50) & (data.sma_50 > data.sma_200) & (data.ema20_slope_10 > 0)
    ma_stress = (close < data.sma_50) & (data.sma_50 < data.sma_200) & (data.ema20_slope_10 < 0)
    output = {
        "current_return20_fixed": np.where(data.return_20 > .02, "bull", np.where(data.return_20 < -.02, "stress", "sideways")),
        "ma_stack_50_200": np.where(ma_bull, "bull", np.where(ma_stress, "stress", "sideways")),
        "momentum_vote_5": np.where(votes >= 3, "bull", np.where(votes <= -3, "stress", "sideways")),
        "adx_confirmed_vote": np.where((data.adx_14 >= 20) & (votes >= 2) & (data.di_direction > 0), "bull", np.where((data.adx_14 >= 20) & (votes <= -2) & (data.di_direction < 0), "stress", "sideways")),
        "volatility_adaptive_composite": np.where((data.return_20 > adaptive) & (votes >= 2), "bull", np.where((data.return_20 < -adaptive) & (votes <= -2), "stress", "sideways")),
    }
    return {name: pd.Series(values, index=data.index) for name, values in output.items()}


def metrics(data: pd.DataFrame, labels: pd.Series, horizon: int) -> dict:
    future = data[f"future_{horizon}"]
    valid = future.notna() & data.sma_200.notna()
    future, labels = future[valid], labels[valid]
    bull, stress, sideways = labels == "bull", labels == "stress", labels == "sideways"
    directional = bull | stress
    correct = ((bull & (future > 0)) | (stress & (future < 0)))
    bull_precision = float((future[bull] > 0).mean()) if bull.any() else None
    stress_precision = float((future[stress] < 0).mean()) if stress.any() else None
    balanced = np.mean([value for value in (bull_precision, stress_precision) if value is not None])
    return {
        "samples": int(valid.sum()), "directional_coverage": float(directional.mean()),
        "directional_accuracy": float(correct[directional].mean()) if directional.any() else None,
        "balanced_precision": float(balanced),
        "bull_precision": bull_precision, "stress_precision": stress_precision,
        "bull_median_return": float(future[bull].median()) if bull.any() else None,
        "stress_median_return": float(future[stress].median()) if stress.any() else None,
        "bull_stress_spread": float(future[bull].median() - future[stress].median()) if bull.any() and stress.any() else None,
        "sideways_median_abs_return": float(future[sideways].abs().median()) if sideways.any() else None,
        "phase_persistence_1d": float((labels == labels.shift()).mean()),
        "counts": labels.value_counts().to_dict(),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbols", nargs="*", default=list(DEFAULT_SYMBOLS))
    parser.add_argument("--years", type=int, default=10)
    parser.add_argument("--output", type=Path, default=Path("reports/market-trend-phase-indicator-test.json"))
    args = parser.parse_args()
    report = {"generated_at": datetime.now(timezone.utc).isoformat(), "years": args.years, "symbols": {}}
    with Database() as database:
        for symbol in args.symbols:
            data = frame(_import(database, symbol, "1d", args.years, persist=False).bars)
            candidates = phases(data)
            report["symbols"][symbol] = {}
            for name, labels in candidates.items():
                report["symbols"][symbol][name] = {
                    str(horizon): metrics(data, labels, horizon) for horizon in HORIZONS
                }
            print(f"PHASE_TEST_READY symbol={symbol} rows={len(data)}", flush=True)
    candidates = next(iter(report["symbols"].values())).keys()
    ranking = []
    for name in candidates:
        rows = [report["symbols"][symbol][name][str(horizon)] for symbol in args.symbols for horizon in HORIZONS]
        ranking.append({
            "candidate": name,
            "mean_balanced_precision": float(np.mean([row["balanced_precision"] for row in rows])),
            "mean_directional_accuracy": float(np.mean([row["directional_accuracy"] for row in rows])),
            "mean_coverage": float(np.mean([row["directional_coverage"] for row in rows])),
            "mean_bull_stress_spread": float(np.mean([row["bull_stress_spread"] for row in rows])),
            "mean_phase_persistence": float(np.mean([row["phase_persistence_1d"] for row in rows])),
        })
    report["ranking"] = sorted(ranking, key=lambda row: (row["mean_balanced_precision"], row["mean_bull_stress_spread"]), reverse=True)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    print(json.dumps(report["ranking"], indent=2))
    print(f"REPORT {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
