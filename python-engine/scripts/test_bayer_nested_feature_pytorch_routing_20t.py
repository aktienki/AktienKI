from __future__ import annotations

import argparse
import json
import math
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd
import torch
from torch import nn
from sklearn.ensemble import HistGradientBoostingRegressor
from sklearn.feature_selection import mutual_info_classif, mutual_info_regression
from sklearn.impute import SimpleImputer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import balanced_accuracy_score
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler as SklearnStandardScaler

from app.cli.main import _import
from app.database.connection import Database
from app.market.benchmark import resolve_benchmark_symbol
from app.models.instrument_market_data import InstrumentMarketData
from app.repositories.instrument_repository import InstrumentRepository
from test_pytorch_regime_routing_20t import PHASES, PHASE_INDEX, fit_gate, gate_probabilities
from test_standard_horizon_regime_experts_3stocks import evaluate, raw_rows, standard_train
from test_stock_regime_ensemble_experts import bars_frame


DEFAULT_HORIZON = 20
MIN_PHASE_ROWS = 150
FEATURE_COUNTS = (12, 20, 30, 45)
TWO_PHASES = ("uptrend", "downtrend")
TWO_PHASE_INDEX = {name: index for index, name in enumerate(TWO_PHASES)}


class LongHorizonNet(nn.Module):
    def __init__(self, feature_count: int):
        super().__init__()
        self.shared = nn.Sequential(
            nn.Linear(feature_count, 48), nn.ReLU(), nn.Dropout(.10),
            nn.Linear(48, 24), nn.ReLU(),
        )
        self.direction = nn.Linear(24, 1)
        self.expected_return = nn.Linear(24, 1)

    def forward(self, values):
        hidden = self.shared(values)
        return self.direction(hidden).squeeze(-1), self.expected_return(hidden).squeeze(-1)


def fit_long_horizon_model(pool: pd.DataFrame, seed: int):
    torch.manual_seed(seed); np.random.seed(seed)
    matrix = np.asarray(pool.X.tolist(), dtype=float)
    imputer = SimpleImputer(strategy="median")
    matrix = imputer.fit_transform(matrix)
    mean = matrix.mean(axis=0); std = matrix.std(axis=0); std[std < 1e-8] = 1
    matrix = ((matrix - mean) / std).astype(np.float32)
    target = pool.y.to_numpy(dtype=np.float32)
    split = max(500, int(len(pool) * .85)); split = min(split, len(pool) - 30)
    model = LongHorizonNet(matrix.shape[1])
    optimizer = torch.optim.AdamW(model.parameters(), lr=8e-4, weight_decay=2e-4)
    train_x = torch.tensor(matrix[:split]); train_y = torch.tensor(target[:split])
    valid_x = torch.tensor(matrix[split:]); valid_y = torch.tensor(target[split:])
    age_days = (pool.index[split - 1] - pool.index[:split]).days.to_numpy(dtype=float)
    weights = torch.tensor(np.power(.5, age_days / (365.25 * 4)), dtype=torch.float32)
    best = float("inf"); state = None; stale = 0
    for _ in range(100):
        model.train(); logits, returns = model(train_x)
        direction = (train_y >= 0).float()
        losses = nn.functional.binary_cross_entropy_with_logits(logits, direction, reduction="none")
        losses += 8 * nn.functional.smooth_l1_loss(returns, train_y, reduction="none")
        loss = (losses * weights).sum() / weights.sum()
        optimizer.zero_grad(); loss.backward(); nn.utils.clip_grad_norm_(model.parameters(), 1); optimizer.step()
        model.eval()
        with torch.no_grad():
            valid_logits, valid_returns = model(valid_x)
            valid_loss = nn.functional.binary_cross_entropy_with_logits(valid_logits, (valid_y >= 0).float())
            valid_loss += 8 * nn.functional.smooth_l1_loss(valid_returns, valid_y)
        value = float(valid_loss)
        if value < best - 1e-4:
            best = value; state = {key: item.detach().clone() for key, item in model.state_dict().items()}; stale = 0
        else:
            stale += 1
            if stale >= 10: break
    if state: model.load_state_dict(state)
    return model, imputer, mean, std


def fit_two_phase_gate(pool: pd.DataFrame, seed: int):
    torch.manual_seed(seed); np.random.seed(seed)
    matrix = np.asarray(pool.X.tolist(), dtype=np.float32)
    target = np.asarray([TWO_PHASE_INDEX[value] for value in pool.economic_phase], dtype=np.int64)
    split = max(100, int(len(matrix) * .85)); split = min(split, len(matrix) - 30)
    mean = matrix[:split].mean(axis=0); std = matrix[:split].std(axis=0); std[std < 1e-8] = 1
    matrix = (matrix - mean) / std
    model = nn.Sequential(nn.Linear(matrix.shape[1], 48), nn.ReLU(), nn.Dropout(.10),
                          nn.Linear(48, 24), nn.ReLU(), nn.Linear(24, 2))
    optimizer = torch.optim.AdamW(model.parameters(), lr=8e-4, weight_decay=2e-4)
    counts = np.bincount(target[:split], minlength=2)
    class_weight = torch.tensor(len(target[:split]) / np.maximum(counts, 1), dtype=torch.float32)
    age_days = (pool.index[split - 1] - pool.index[:split]).days.to_numpy(dtype=float)
    recency = torch.tensor(np.power(.5, age_days / (365.25 * 4)), dtype=torch.float32)
    tx = torch.tensor(matrix[:split]); ty = torch.tensor(target[:split])
    vx = torch.tensor(matrix[split:]); vy = torch.tensor(target[split:])
    best = float("inf"); state = None; stale = 0
    for _ in range(80):
        model.train(); logits = model(tx)
        loss = (nn.functional.cross_entropy(logits, ty, weight=class_weight, reduction="none") * recency).sum() / recency.sum()
        optimizer.zero_grad(); loss.backward(); nn.utils.clip_grad_norm_(model.parameters(), 1); optimizer.step()
        model.eval()
        with torch.no_grad(): value = float(nn.functional.cross_entropy(model(vx), vy, weight=class_weight))
        if value < best - 1e-4:
            best = value; state = {key: item.detach().clone() for key, item in model.state_dict().items()}; stale = 0
        else:
            stale += 1
            if stale >= 8: break
    if state: model.load_state_dict(state)
    return model, mean, std


def two_phase_probabilities(model, mean, std, frame: pd.DataFrame) -> np.ndarray:
    matrix = (np.asarray(frame.X.tolist(), dtype=np.float32) - mean) / std
    model.eval()
    with torch.no_grad():
        return torch.softmax(model(torch.tensor(matrix)), dim=1).numpy()


def point_in_time_long_horizon(long_data: pd.DataFrame) -> tuple[pd.DataFrame, list[dict]]:
    rows = []
    diagnostics = []
    for year in sorted(set(long_data.index.year)):
        start = pd.Timestamp(f"{year}-01-01")
        pool = long_data[long_data.index < start - pd.offsets.BDay(60)]
        test = long_data[long_data.index.year == year]
        if len(pool) < 1000 or len(test) < 20:
            continue
        model, imputer, mean, std = fit_long_horizon_model(pool, 6000 + year)
        matrix = imputer.transform(np.asarray(test.X.tolist(), dtype=float))
        matrix = ((matrix - mean) / std).astype(np.float32)
        model.eval()
        with torch.no_grad():
            logits, expected = model(torch.tensor(matrix))
            probability = torch.sigmoid(logits).numpy(); expected = expected.numpy()
        actual = test.y.to_numpy(dtype=float)
        diagnostics.append({
            "year": year, "training_samples": len(pool), "samples": len(test),
            "direction_accuracy": float(((probability >= .5) == (actual >= 0)).mean()),
        })
        rows.extend({"date": date, "pytorch_60d_expected_return": float(expected[index]),
                     "pytorch_60d_up_probability": float(probability[index])}
                    for index, date in enumerate(test.index))
        print("POINT_IN_TIME_60T", year, len(test), flush=True)
    frame = pd.DataFrame(rows).set_index("date") if rows else pd.DataFrame()
    return frame, diagnostics


def append_long_horizon_features(data: pd.DataFrame, meta: pd.DataFrame, names: list[str]) -> pd.DataFrame:
    common = data.index.intersection(meta.index)
    result = data.loc[common].copy()
    aligned = meta.loc[common]
    result["X"] = [list(values) + [float(aligned.iloc[index].pytorch_60d_expected_return),
                                    float(aligned.iloc[index].pytorch_60d_up_probability)]
                   for index, values in enumerate(result.X)]
    result.attrs["feature_names"] = names + ["pytorch_60d_expected_return", "pytorch_60d_up_probability"]
    return result


def subset(frame: pd.DataFrame, indices: list[int], names: list[str]) -> pd.DataFrame:
    result = frame.copy()
    result["X"] = [np.asarray(row, dtype=float)[indices].tolist() for row in frame.X]
    result.attrs["feature_names"] = [names[index] for index in indices]
    return result


def inner_parts(pool: pd.DataFrame) -> tuple[pd.DataFrame, pd.DataFrame]:
    years = sorted(set(pool.index.year))
    valid_years = years[-2:]
    train = pool[~pool.index.year.isin(valid_years)]
    valid = pool[pool.index.year.isin(valid_years)]
    return train, valid


def ranked_indices(train: pd.DataFrame, target: np.ndarray, classification: bool) -> list[int]:
    matrix = np.asarray(train.X.tolist(), dtype=float)
    matrix = SimpleImputer(strategy="median").fit_transform(matrix)
    if classification:
        scores = mutual_info_classif(matrix, target, random_state=42)
    else:
        scores = mutual_info_regression(matrix, target, random_state=42)
    return np.argsort(np.nan_to_num(scores, nan=0.0))[::-1].tolist()


def choose_expert_features(pool: pd.DataFrame, names: list[str]) -> tuple[list[int], dict]:
    train, valid = inner_parts(pool)
    ranking = ranked_indices(train, train.y.to_numpy(), False)
    candidates = []
    for count in FEATURE_COUNTS:
        indices = ranking[: min(count, len(names))]
        tx = np.asarray(subset(train, indices, names).X.tolist())
        vx = np.asarray(subset(valid, indices, names).X.tolist())
        model = HistGradientBoostingRegressor(max_iter=180, learning_rate=.05, max_leaf_nodes=15,
                                              l2_regularization=.15, random_state=42)
        age = np.arange(len(train) - 1, -1, -1)
        weights = np.exp(-age / (252 * 4))
        model.fit(tx, train.y.to_numpy(), sample_weight=weights)
        pred = model.predict(vx)
        direction = float(((valid.y.to_numpy() >= 0) == (pred >= 0)).mean())
        mae = float(np.abs(valid.y.to_numpy() - pred).mean())
        score = direction - .35 * mae
        candidates.append((score, direction, mae, indices))
    best = max(candidates, key=lambda item: item[0])
    return best[3], {"selected_count": len(best[3]), "validation_direction_accuracy": best[1],
                     "validation_mae": best[2], "feature_names": [names[i] for i in best[3]]}


def choose_gate_features(pool: pd.DataFrame, names: list[str]) -> tuple[list[int], dict]:
    train, valid = inner_parts(pool)
    target = np.asarray([PHASE_INDEX[value] for value in train.economic_phase], dtype=int)
    ranking = ranked_indices(train, target, True)
    candidates = []
    for count in FEATURE_COUNTS:
        indices = ranking[: min(count, len(names))]
        model = make_pipeline(SimpleImputer(strategy="median"), SklearnStandardScaler(),
                              LogisticRegression(max_iter=1200, class_weight="balanced", C=.5))
        age = np.arange(len(train) - 1, -1, -1)
        weights = np.exp(-age / (252 * 4))
        model.fit(np.asarray(subset(train, indices, names).X.tolist()), target,
                  logisticregression__sample_weight=weights)
        predicted = model.predict(np.asarray(subset(valid, indices, names).X.tolist()))
        truth = np.asarray([PHASE_INDEX[value] for value in valid.economic_phase], dtype=int)
        score = float(balanced_accuracy_score(truth, predicted))
        candidates.append((score, indices))
    best = max(candidates, key=lambda item: item[0])
    return best[1], {"selected_count": len(best[1]), "validation_balanced_accuracy": best[0],
                     "feature_names": [names[i] for i in best[1]]}


def accepted_evaluation(actual: np.ndarray, predicted: np.ndarray, accepted: np.ndarray) -> dict:
    result = evaluate(actual[accepted], predicted[accepted]) if accepted.any() else {
        "samples": 0, "direction_accuracy": None, "mae": None, "trades": 0,
        "win_rate": None, "profit_factor": None, "total_return": 0.0,
    }
    result["coverage"] = float(accepted.mean())
    result["wait_samples"] = int((~accepted).sum())
    return result


def finite(value):
    return value is not None and isinstance(value, (int, float)) and math.isfinite(value)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", default="BAYN.DE")
    parser.add_argument("--horizon", type=int, default=DEFAULT_HORIZON)
    parser.add_argument("--years", type=int, default=30)
    parser.add_argument("--test-years", nargs="*", type=int, default=list(range(2018, 2027)))
    parser.add_argument("--phase-threshold", type=float, default=.60)
    parser.add_argument("--point-in-time-60t-feature", action="store_true")
    parser.add_argument("--two-phase", action="store_true")
    parser.add_argument("--momentum-router", action="store_true")
    parser.add_argument("--momentum-threshold", type=float, default=.01)
    parser.add_argument("--ema-confirmation", action="store_true")
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    horizon = args.horizon

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
        us2y = [InstrumentMarketData(instrument_id=us2y_instrument.id, timeframe="1d",
                 timestamp=row["bar_time"], open=row["open"], high=row["high"], low=row["low"],
                 close=row["close"], volume=int(row["volume"] or 0)) for row in rows]

    data = raw_rows(bars, benchmark_bars, {"AGG": agg, "US2Y": us2y}, horizon)
    all_names = list(data.attrs["feature_names"])
    data.attrs.clear()
    long_horizon_diagnostics = []
    if args.point_in_time_60t_feature:
        long_data = raw_rows(bars, benchmark_bars, {"AGG": agg, "US2Y": us2y}, 60)
        long_data.attrs.clear()
        long_meta, long_horizon_diagnostics = point_in_time_long_horizon(long_data)
        data = append_long_horizon_features(data, long_meta, all_names)
        all_names = list(data.attrs["feature_names"])
        data.attrs.clear()
    market = bars_frame(benchmark_bars, "market")
    benchmark_close = market.attrs["close"].reindex(data.index, method="ffill")
    forward = benchmark_close.shift(-horizon) / benchmark_close - 1
    phases = TWO_PHASES if (args.two_phase or args.momentum_router) else PHASES
    phase_index = TWO_PHASE_INDEX if (args.two_phase or args.momentum_router) else PHASE_INDEX
    if args.momentum_router:
        stock_close = bars_frame(bars, "stock").attrs["close"].reindex(data.index, method="ffill")
        momentum = (stock_close / stock_close.shift(20) - 1).ewm(span=5, adjust=False).mean()
        phase_labels = pd.Series("neutral", index=data.index, dtype=object)
        if args.ema_confirmation:
            ema20 = stock_close.ewm(span=20, adjust=False).mean()
            ema50 = stock_close.ewm(span=50, adjust=False).mean()
            ema20_slope = ema20.pct_change(5)
            up_mask = (momentum > args.momentum_threshold) & (ema20 > ema50) & (ema20_slope > 0)
            down_mask = (momentum < -args.momentum_threshold) & (ema20 < ema50) & (ema20_slope < 0)
        else:
            up_mask = momentum > args.momentum_threshold
            down_mask = momentum < -args.momentum_threshold
        phase_labels.loc[up_mask] = "uptrend"
        phase_labels.loc[down_mask] = "downtrend"
        data["economic_phase"] = phase_labels
        data.loc[momentum.isna(), "economic_phase"] = np.nan
    else:
        data["economic_phase"] = (np.where(forward >= 0, "uptrend", "downtrend") if args.two_phase
                                  else np.where(forward > .02, "bull", np.where(forward < -.02, "stress", "sideways")))
        data.loc[forward.isna(), "economic_phase"] = np.nan
    data = data.dropna(subset=["economic_phase"])

    report = {"symbol": args.symbol, "benchmark": benchmark, "horizon": horizon,
              "years": args.years, "phase_threshold": args.phase_threshold,
              "routing": "pytorch_hard_no_fallback_wait_below_threshold",
              "phase_design": (("causal_stock_momentum20_ema5_plus_ema20_50_slope5" if args.ema_confirmation
                                else "causal_stock_momentum20_ema5") if args.momentum_router
                               else ("two_phase_uptrend_downtrend" if args.two_phase else "three_phase")),
              "momentum_threshold": args.momentum_threshold if args.momentum_router else None,
              "selection": "nested_mutual_information_plus_inner_two_year_validation",
              "point_in_time_60t_feature": args.point_in_time_60t_feature,
              "long_horizon_diagnostics": long_horizon_diagnostics,
              "generated_at": datetime.now(timezone.utc).isoformat(), "folds": []}

    for year in args.test_years:
        start = pd.Timestamp(f"{year}-01-01")
        pool = data[data.index < start - pd.offsets.BDay(horizon)]
        test = data[data.index.year == year]
        if len(pool) < 1000 or len(test) < 30:
            continue

        global_indices, global_selection = choose_expert_features(pool, all_names)
        global_pool = subset(pool, global_indices, all_names)
        global_test = subset(test, global_indices, all_names)
        global_model, global_scaler, _, _ = standard_train(global_pool, horizon,
                                                            global_selection["feature_names"])
        global_pred = np.asarray(global_model.model.predict(global_scaler.transform(global_test.X.tolist())))

        phase_predictions: dict[str, np.ndarray] = {}
        phase_details = {}
        for phase in phases:
            phase_pool = pool[pool.economic_phase == phase]
            if len(phase_pool) < MIN_PHASE_ROWS:
                phase_details[phase] = {"available": False, "rows": len(phase_pool)}
                continue
            indices, selection = choose_expert_features(phase_pool, all_names)
            selected_pool = subset(phase_pool, indices, all_names)
            selected_test = subset(test, indices, all_names)
            champion, scaler, candidates, refit_rows = standard_train(selected_pool, horizon,
                                                                       selection["feature_names"])
            phase_predictions[phase] = np.asarray(champion.model.predict(
                scaler.transform(selected_test.X.tolist())))
            phase_details[phase] = {"available": True, "rows": len(phase_pool),
                                    "champion": champion.model_name, "model_candidates": candidates,
                                    "refit_rows": refit_rows, **selection}

        if args.momentum_router:
            probabilities = np.ones((len(test), 2), dtype=float)
            selected_phase = test.economic_phase.to_numpy(dtype=str)
            selected_index = np.asarray([phase_index.get(value, -1) for value in selected_phase], dtype=int)
            confidence = np.ones(len(test), dtype=float)
            gate_selection = {"method": ("causal_stock_momentum20_ema5_plus_ema20_50_slope5"
                                          if args.ema_confirmation else "causal_stock_momentum20_ema5"),
                              "threshold": args.momentum_threshold}
        elif args.two_phase:
            gate_indices = list(range(len(all_names)))
            gate_selection = {"selected_count": len(all_names), "feature_names": all_names,
                              "method": "all_features_pytorch_binary_gate"}
            gate_pool = pool
            gate_test = test
            gate, mean, std = fit_two_phase_gate(gate_pool, 42 + year)
            probabilities = two_phase_probabilities(gate, mean, std, gate_test)
        else:
            gate_indices, gate_selection = choose_gate_features(pool, all_names)
            gate_pool = subset(pool, gate_indices, all_names)
            gate_test = subset(test, gate_indices, all_names)
            gate, mean, std = fit_gate(gate_pool, gate_selection["feature_names"], 42 + year)
            probabilities = gate_probabilities(gate, mean, std, gate_test)
        if not args.momentum_router:
            selected_index = probabilities.argmax(axis=1)
            confidence = probabilities.max(axis=1)
            selected_phase = np.asarray([phases[index] for index in selected_index])
        accepted = confidence >= args.phase_threshold
        accepted &= np.asarray([phase in phase_predictions for phase in selected_phase])
        hard_pred = np.zeros(len(test), dtype=float)
        for phase, predictions in phase_predictions.items():
            mask = selected_phase == phase
            hard_pred[mask] = predictions[mask]

        truth_phase = np.asarray([phase_index.get(value, -1) for value in test.economic_phase])
        fold = {"year": year, "samples": len(test), "global": evaluate(test.y.to_numpy(), global_pred),
                "pytorch_hard_no_fallback": accepted_evaluation(test.y.to_numpy(), hard_pred, accepted),
                "gate_accuracy": float((selected_index == truth_phase).mean()),
                "accepted_gate_accuracy": float((selected_index[accepted] == truth_phase[accepted]).mean()) if accepted.any() else None,
                "mean_phase_confidence": float(confidence.mean()), "global_selection": global_selection,
                "gate_selection": gate_selection, "phase_models": phase_details,
                "selected_phase_counts": {phase: int((selected_phase == phase).sum()) for phase in phases}}
        report["folds"].append(fold)
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
        summary = {"global_acc": fold["global"]["direction_accuracy"],
                   "hard_acc": fold["pytorch_hard_no_fallback"]["direction_accuracy"],
                   "coverage": fold["pytorch_hard_no_fallback"]["coverage"],
                   "hard_pf": fold["pytorch_hard_no_fallback"]["profit_factor"]}
        print("BAYER_ROUTING_FOLD", year, json.dumps(summary), flush=True)

    print("REPORT", args.output, flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
