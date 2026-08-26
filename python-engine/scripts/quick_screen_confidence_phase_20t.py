from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd

from app.cli.main import _import
from app.database.connection import Database
from app.repositories.instrument_repository import InstrumentRepository

from test_standard_horizon_regime_experts_3stocks import evaluate, raw_rows
from test_stock_regime_ensemble_experts import PHASES, bars_frame, estimator_set


HORIZON = 20
ROUND_TRIP_COST = 0.005


def effective_profit_factor(metrics: dict) -> float:
    value = metrics.get("independent_profit_factor")
    if value is not None:
        return float(value)
    # No losing independent trade means an infinite, not a failed, PF.
    if (int(metrics.get("independent_trades") or 0) > 0
            and float(metrics.get("independent_win_rate") or 0) >= 1.0
            and float(metrics.get("independent_mean_return") or 0) > 0):
        return 999.0
    return 0.0


def fit_phase_ensembles(train: pd.DataFrame, minimum_phase_samples: int) -> dict[str, list]:
    ensembles: dict[str, list] = {}
    age_days = (train.index.max() - train.index).days.to_numpy(dtype=float)
    # Recent structures deliberately dominate without discarding older regimes:
    # an observation loses half of its weight after two years.
    weights = np.power(0.5, age_days / (365.25 * 2.0))
    for phase_index, phase in enumerate(PHASES):
        mask = train.phase.to_numpy() == phase
        if int(mask.sum()) < minimum_phase_samples:
            continue
        X = np.asarray(train.loc[mask, "X"].tolist(), dtype=float)
        y = train.loc[mask, "y"].to_numpy(dtype=float)
        lower, upper = np.quantile(y, [.01, .99])
        models = estimator_set(4200 + phase_index * 100)
        for model in models:
            model.fit(X, np.clip(y, lower, upper), sample_weight=weights[mask])
        ensembles[phase] = models
    return ensembles


def predict(ensembles: dict[str, list], frame: pd.DataFrame) -> tuple[np.ndarray, np.ndarray]:
    expected = np.full(len(frame), -1.0, dtype=float)
    confidence = np.zeros(len(frame), dtype=float)
    phases = frame.phase.to_numpy()
    for phase, models in ensembles.items():
        positions = np.flatnonzero(phases == phase)
        if not len(positions):
            continue
        X = np.asarray(frame.iloc[positions].X.tolist(), dtype=float)
        matrix = np.asarray([model.predict(X) for model in models])
        expected[positions] = np.median(matrix, axis=0)
        # Point-in-time model consensus: percentage of independently trained
        # quick-screen models expecting at least +1% gross over 20 days.
        confidence[positions] = 100.0 * (matrix >= .01).mean(axis=0)
    return expected, confidence


def threshold_metrics(frame: pd.DataFrame, expected: np.ndarray, confidence: np.ndarray, threshold: float) -> dict:
    filtered = np.where(confidence >= threshold, expected, -1.0)
    return evaluate(frame.y.to_numpy(dtype=float), filtered, HORIZON)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", required=True)
    parser.add_argument("--years", type=int, default=7)
    parser.add_argument("--test-year", type=int, default=2025)
    parser.add_argument("--minimum-phase-samples", type=int, default=120)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    started = datetime.now(timezone.utc)
    with Database() as database:
        repository = InstrumentRepository(database)
        instrument = repository.find_by_symbol(args.symbol)
        bars = list(_import(database, args.symbol, "1d", args.years, persist=False).bars)
    rows = raw_rows(bars, bars, {}, HORIZON, stock_only=True)
    rows.attrs.clear()
    rows["phase"] = bars_frame(bars, "stock_phase").attrs["phase"].reindex(rows.index)
    rows = rows.dropna(subset=["phase"])
    test_start = pd.Timestamp(f"{args.test_year}-01-01")
    five_year_start = test_start - pd.DateOffset(years=4)
    calibration_start = test_start - pd.DateOffset(years=1)
    selection_train = rows[(rows.index >= five_year_start) & (rows.index < calibration_start - pd.offsets.BDay(HORIZON))]
    calibration = rows[(rows.index >= calibration_start) & (rows.index < test_start)]
    test = rows[rows.index.year == args.test_year]
    selection_ensembles = fit_phase_ensembles(selection_train, args.minimum_phase_samples)
    calibration_prediction, calibration_confidence = predict(selection_ensembles, calibration)
    candidates = []
    for threshold in (0.0, 34.0, 67.0, 100.0):
        metrics = threshold_metrics(calibration, calibration_prediction, calibration_confidence, threshold)
        candidates.append({"minimum_confidence": threshold, "metrics": metrics})
    eligible = [item for item in candidates if item["metrics"]["independent_trades"] >= 3
                and effective_profit_factor(item["metrics"]) >= 1.10
                and float(item["metrics"]["independent_mean_return"] or 0) > 0]
    ranked = eligible or candidates
    chosen = max(ranked, key=lambda item: (
        effective_profit_factor(item["metrics"]),
        float(item["metrics"]["independent_mean_return"] or -1),
        item["metrics"]["independent_trades"],
    ))
    full_train = rows[(rows.index >= five_year_start) & (rows.index < test_start - pd.offsets.BDay(HORIZON))]
    final_ensembles = fit_phase_ensembles(full_train, args.minimum_phase_samples)
    test_prediction, test_confidence = predict(final_ensembles, test)
    unfiltered_validation = threshold_metrics(test, test_prediction, test_confidence, 0.0)
    validation = threshold_metrics(test, test_prediction, test_confidence, chosen["minimum_confidence"])
    raw_executable_trades = int(unfiltered_validation["independent_trades"] or 0)
    filtered_executable_trades = int(validation["independent_trades"] or 0)
    retention = filtered_executable_trades / raw_executable_trades if raw_executable_trades else 0.0
    raw_pf = effective_profit_factor(unfiltered_validation)
    filtered_pf = effective_profit_factor(validation)
    pf_improved = filtered_pf + 1e-9 >= raw_pf
    eligible_after_confidence = (
        raw_executable_trades >= 8
        and filtered_executable_trades >= 3
        and filtered_pf >= 1.10
        and pf_improved
        and float(validation["independent_mean_return"] or 0) > 0
    )
    trades = int(validation["independent_trades"] or 0)
    pf = effective_profit_factor(validation)
    quality = ("quality" if pf >= 1.50 else "solid" if pf >= 1.25 else "basic" if pf >= 1.10 else "unqualified")
    evidence = ("high" if trades >= 20 else "good" if trades >= 10 else "limited" if trades >= 5 else "experimental")
    report = {
        "symbol": args.symbol,
        "instrument_id": instrument.id,
        "pipeline": "quick-screen-phase-confidence-v1",
        "horizon": HORIZON,
        "training_window": "first four years with final year untouched",
        "confidence_definition": "percentage of three phase models forecasting >=1% gross return",
        "cost": ROUND_TRIP_COST,
        "selection_train": {"start": str(selection_train.index.min().date()), "end": str(selection_train.index.max().date()), "samples": len(selection_train)},
        "calibration": {"start": str(calibration.index.min().date()), "end": str(calibration.index.max().date()), "samples": len(calibration), "candidates": candidates},
        "chosen_confidence": chosen["minimum_confidence"],
        "test": {"year": args.test_year, "samples": len(test), "unfiltered_metrics": unfiltered_validation, "metrics": validation},
        "selectivity": {
            "raw_executable_trades": raw_executable_trades,
            "filtered_executable_trades": filtered_executable_trades,
            "retention_ratio": retention,
            "raw_profit_factor": raw_pf,
            "filtered_profit_factor": filtered_pf,
            "profit_factor_improved": pf_improved,
        },
        "trained_phases": sorted(final_ensembles),
        "decision": "candidate_pending_context_filters" if eligible_after_confidence else "documented_not_profitable",
        "quality_class_before_context_filters": quality,
        "evidence_class": evidence,
        "thresholds": {"minimum_profit_factor": 1.10, "minimum_raw_executed_trades": 8, "minimum_filtered_trades_for_full_validation": 3, "positive_mean_return": True},
        "duration_seconds": (datetime.now(timezone.utc) - started).total_seconds(),
        "generated_at": datetime.now(timezone.utc).isoformat(),
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False))
    print("QUICK_SCREEN", json.dumps({"symbol": args.symbol, "confidence": report["chosen_confidence"], "test": validation, "decision": report["decision"]}), flush=True)
    print("REPORT", args.output, flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
