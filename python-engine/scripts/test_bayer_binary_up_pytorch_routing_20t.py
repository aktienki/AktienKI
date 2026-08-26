from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd
import torch
from sklearn.impute import SimpleImputer
from sklearn.metrics import balanced_accuracy_score
from torch import nn

from app.cli.main import _import
from app.database.connection import Database
from app.market.benchmark import resolve_benchmark_symbol
from app.models.instrument_market_data import InstrumentMarketData
from app.repositories.instrument_repository import InstrumentRepository
from test_bayer_nested_feature_pytorch_routing_20t import (
    accepted_evaluation,
    choose_expert_features,
    subset,
)
from test_standard_horizon_regime_experts_3stocks import evaluate, raw_rows, standard_train


VOLATILITY_TOKENS = (
    "ATR_", "VOL_", "BOLLINGER_", "DRAWDOWN", "BETA_", "KELTNER_",
)


class UpGate(nn.Module):
    def __init__(self, feature_count: int):
        super().__init__()
        self.network = nn.Sequential(
            nn.Linear(feature_count, 48), nn.ReLU(), nn.Dropout(.12),
            nn.Linear(48, 24), nn.ReLU(), nn.Dropout(.08),
            nn.Linear(24, 1),
        )

    def forward(self, values: torch.Tensor) -> torch.Tensor:
        return self.network(values).squeeze(-1)


def gate_indices(names: list[str]) -> list[int]:
    # Volatility is intentionally mandatory; the other features retain market,
    # trend, momentum, liquidity and macro context.
    volatility = [index for index, name in enumerate(names)
                  if any(token in name.upper() for token in VOLATILITY_TOKENS)]
    remaining = [index for index in range(len(names)) if index not in volatility]
    return volatility + remaining


def fit_up_gate(pool: pd.DataFrame, names: list[str], seed: int):
    years = sorted(set(pool.index.year))
    valid_years = years[-2:]
    train = pool[~pool.index.year.isin(valid_years)]
    valid = pool[pool.index.year.isin(valid_years)]
    indices = gate_indices(names)
    imputer = SimpleImputer(strategy="median")
    train_x = imputer.fit_transform(np.asarray(subset(train, indices, names).X.tolist(), dtype=float))
    valid_x = imputer.transform(np.asarray(subset(valid, indices, names).X.tolist(), dtype=float))
    mean = train_x.mean(axis=0); std = train_x.std(axis=0); std[std < 1e-8] = 1
    train_x = ((train_x - mean) / std).astype(np.float32)
    valid_x = ((valid_x - mean) / std).astype(np.float32)
    train_y = (train.y.to_numpy(dtype=float) > 0).astype(np.float32)
    valid_y = (valid.y.to_numpy(dtype=float) > 0).astype(np.float32)

    torch.manual_seed(seed); np.random.seed(seed)
    model = UpGate(train_x.shape[1])
    optimizer = torch.optim.AdamW(model.parameters(), lr=7e-4, weight_decay=3e-4)
    age_days = (train.index[-1] - train.index).days.to_numpy(dtype=float)
    weights = torch.tensor(np.power(.5, age_days / (365.25 * 4)), dtype=torch.float32)
    tx = torch.tensor(train_x); ty = torch.tensor(train_y)
    vx = torch.tensor(valid_x); vy = torch.tensor(valid_y)
    positive = max(1.0, float((train_y == 0).sum() / max(1, (train_y == 1).sum())))
    best_loss = float("inf"); best_state = None; stale = 0
    for _ in range(140):
        model.train(); logits = model(tx)
        losses = nn.functional.binary_cross_entropy_with_logits(
            logits, ty, reduction="none", pos_weight=torch.tensor(positive))
        loss = (losses * weights).sum() / weights.sum()
        optimizer.zero_grad(); loss.backward(); nn.utils.clip_grad_norm_(model.parameters(), 1); optimizer.step()
        model.eval()
        with torch.no_grad():
            value = float(nn.functional.binary_cross_entropy_with_logits(model(vx), vy))
        if value < best_loss - 1e-4:
            best_loss = value
            best_state = {key: item.detach().clone() for key, item in model.state_dict().items()}
            stale = 0
        else:
            stale += 1
            if stale >= 14:
                break
    if best_state:
        model.load_state_dict(best_state)
    model.eval()
    with torch.no_grad():
        valid_probability = torch.sigmoid(model(vx)).numpy()

    # Threshold is selected on past validation data only. Prefer positive edge,
    # then coverage, while requiring a useful minimum sample size.
    candidates = []
    for threshold in np.arange(.50, .81, .025):
        accepted = valid_probability >= threshold
        if accepted.sum() < max(30, int(len(valid) * .08)):
            continue
        hit_rate = float(valid_y[accepted].mean())
        score = hit_rate - .50 + .08 * float(accepted.mean())
        candidates.append((score, hit_rate, float(accepted.mean()), float(threshold)))
    selected = max(candidates, key=lambda item: item[0]) if candidates else (0, None, 0, .60)

    # Refit on the full causal pool with the same preprocessing and architecture.
    full_x = imputer.fit_transform(np.asarray(subset(pool, indices, names).X.tolist(), dtype=float))
    mean = full_x.mean(axis=0); std = full_x.std(axis=0); std[std < 1e-8] = 1
    full_x = ((full_x - mean) / std).astype(np.float32)
    full_y = (pool.y.to_numpy(dtype=float) > 0).astype(np.float32)
    torch.manual_seed(seed + 1000)
    final = UpGate(full_x.shape[1]); optimizer = torch.optim.AdamW(final.parameters(), lr=7e-4, weight_decay=3e-4)
    age_days = (pool.index[-1] - pool.index).days.to_numpy(dtype=float)
    weights = torch.tensor(np.power(.5, age_days / (365.25 * 4)), dtype=torch.float32)
    fx = torch.tensor(full_x); fy = torch.tensor(full_y)
    positive = max(1.0, float((full_y == 0).sum() / max(1, (full_y == 1).sum())))
    for _ in range(55):
        final.train(); losses = nn.functional.binary_cross_entropy_with_logits(
            final(fx), fy, reduction="none", pos_weight=torch.tensor(positive))
        loss = (losses * weights).sum() / weights.sum()
        optimizer.zero_grad(); loss.backward(); nn.utils.clip_grad_norm_(final.parameters(), 1); optimizer.step()
    return final, imputer, mean, std, indices, {
        "threshold": selected[3], "validation_buy_hit_rate": selected[1],
        "validation_coverage": selected[2], "validation_balanced_accuracy": float(
            balanced_accuracy_score(valid_y, valid_probability >= .5)),
        "volatility_feature_count": sum(any(token in names[index].upper() for token in VOLATILITY_TOKENS)
                                        for index in indices),
        "feature_count": len(indices),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", default="BAYN.DE")
    parser.add_argument("--horizon", type=int, default=20)
    parser.add_argument("--years", type=int, default=30)
    parser.add_argument("--test-years", nargs="*", type=int, default=list(range(2018, 2027)))
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()

    with Database() as database:
        repository = InstrumentRepository(database)
        instrument = repository.find_by_symbol(args.symbol)
        benchmark = resolve_benchmark_symbol(instrument, "auto")
        bars = list(_import(database, args.symbol, "1d", args.years, persist=False).bars)
        benchmark_bars = list(_import(database, benchmark, "1d", args.years, persist=False).bars)
        agg = list(_import(database, "AGG", "1d", args.years, persist=False).bars)
        us2y_instrument = repository.find_by_symbol("US2Y")
        rows = database.fetch_all(
            "SELECT bar_time,open,high,low,close,volume FROM price_bars "
            "WHERE instrument_id=%s AND interval='1d' ORDER BY bar_time", (us2y_instrument.id,))
        us2y = [InstrumentMarketData(
            instrument_id=us2y_instrument.id, timeframe="1d", timestamp=row["bar_time"],
            open=row["open"], high=row["high"], low=row["low"], close=row["close"],
            volume=int(row["volume"] or 0)) for row in rows]

    data = raw_rows(bars, benchmark_bars, {"AGG": agg, "US2Y": us2y}, args.horizon)
    names = list(data.attrs["feature_names"]); data.attrs.clear()
    report = {
        "symbol": args.symbol, "benchmark": benchmark, "horizon": args.horizon,
        "routing": "binary_pytorch_up_gate_with_mandatory_volatility_no_fallback",
        "generated_at": datetime.now(timezone.utc).isoformat(), "folds": [],
    }
    for year in args.test_years:
        start = pd.Timestamp(f"{year}-01-01")
        pool = data[data.index < start - pd.offsets.BDay(args.horizon)]
        test = data[data.index.year == year]
        if len(pool) < 1000 or len(test) < 30:
            continue
        expert_indices, selection = choose_expert_features(pool, names)
        expert_pool = subset(pool, expert_indices, names)
        expert_test = subset(test, expert_indices, names)
        expert, scaler, _, _ = standard_train(expert_pool, args.horizon, selection["feature_names"])
        prediction = np.asarray(expert.model.predict(scaler.transform(expert_test.X.tolist())))

        gate, imputer, mean, std, indices, gate_details = fit_up_gate(pool, names, 4200 + year)
        matrix = imputer.transform(np.asarray(subset(test, indices, names).X.tolist(), dtype=float))
        matrix = ((matrix - mean) / std).astype(np.float32)
        gate.eval()
        with torch.no_grad():
            probability = torch.sigmoid(gate(torch.tensor(matrix))).numpy()
        accepted = (probability >= gate_details["threshold"]) & (prediction > 0)
        actual = test.y.to_numpy(dtype=float)
        routed = accepted_evaluation(actual, prediction, accepted)
        routed["buy_hit_rate"] = float((actual[accepted] > 0).mean()) if accepted.any() else None
        fold = {
            "year": year, "samples": len(test), "global": evaluate(actual, prediction),
            "binary_up_routed": routed, "gate": gate_details,
            "mean_up_probability": float(probability.mean()), "expert_selection": selection,
        }
        report["folds"].append(fold)
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
        print("BINARY_UP_FOLD", year, json.dumps({
            "global_accuracy": fold["global"]["direction_accuracy"],
            "routed_accuracy": routed["direction_accuracy"], "buy_hit_rate": routed["buy_hit_rate"],
            "profit_factor": routed["profit_factor"], "coverage": routed["coverage"],
            "threshold": gate_details["threshold"],
        }), flush=True)
    print("REPORT", args.output, flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
