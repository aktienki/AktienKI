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

from quick_screen_confidence_phase_20t import effective_profit_factor, fit_phase_ensembles, predict, threshold_metrics
from test_standard_horizon_regime_experts_3stocks import raw_rows
from test_stock_regime_ensemble_experts import bars_frame


HORIZON = 20


def run_fold(rows: pd.DataFrame, test_year: int, training_years: int, minimum_phase_samples: int) -> dict:
    test_start = pd.Timestamp(f"{test_year}-01-01")
    training_start = test_start - pd.DateOffset(years=training_years)
    calibration_start = test_start - pd.DateOffset(years=1)
    selection_train = rows[(rows.index >= training_start) & (rows.index < calibration_start - pd.offsets.BDay(HORIZON))]
    calibration = rows[(rows.index >= calibration_start) & (rows.index < test_start)]
    test = rows[rows.index.year == test_year]
    selection_models = fit_phase_ensembles(selection_train, minimum_phase_samples)
    calibration_prediction, calibration_confidence = predict(selection_models, calibration)
    candidates = []
    for threshold in (0.0, 34.0, 67.0, 100.0):
        metrics = threshold_metrics(calibration, calibration_prediction, calibration_confidence, threshold)
        candidates.append({"minimum_confidence": threshold, "metrics": metrics})
    eligible = [item for item in candidates if item["metrics"]["independent_trades"] >= 6
                and effective_profit_factor(item["metrics"]) >= 1.10
                and float(item["metrics"]["independent_mean_return"] or 0) > 0]
    chosen = max(eligible or candidates, key=lambda item: (
        effective_profit_factor(item["metrics"]),
        float(item["metrics"]["independent_mean_return"] or -1),
        item["metrics"]["independent_trades"],
    ))
    full_train = rows[(rows.index >= training_start) & (rows.index < test_start - pd.offsets.BDay(HORIZON))]
    final_models = fit_phase_ensembles(full_train, minimum_phase_samples)
    prediction, confidence = predict(final_models, test)
    metrics = threshold_metrics(test, prediction, confidence, chosen["minimum_confidence"])
    return {
        "test_year": test_year,
        "training_start": str(training_start.date()),
        "training_samples": len(full_train),
        "calibration_samples": len(calibration),
        "test_samples": len(test),
        "trained_phases": sorted(final_models),
        "chosen_confidence": chosen["minimum_confidence"],
        "calibration_candidates": candidates,
        "metrics": metrics,
        "profitable": effective_profit_factor(metrics) >= 1.10
                      and float(metrics["independent_mean_return"] or 0) > 0,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", required=True)
    parser.add_argument("--years", type=int, default=10)
    parser.add_argument("--training-years", type=int, default=5)
    parser.add_argument("--test-years", nargs="+", type=int)
    parser.add_argument("--minimum-phase-samples", type=int, default=150)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    started = datetime.now(timezone.utc)
    test_years = args.test_years or list(range(started.year - 4, started.year))
    with Database() as database:
        repository = InstrumentRepository(database)
        instrument = repository.find_by_symbol(args.symbol)
        bars = list(_import(database, args.symbol, "1d", args.years, persist=False).bars)
    rows = raw_rows(bars, bars, {}, HORIZON, stock_only=True)
    rows.attrs.clear()
    rows["phase"] = bars_frame(bars, "stock_phase").attrs["phase"].reindex(rows.index)
    rows = rows.dropna(subset=["phase"])
    report = {
        "symbol": args.symbol,
        "instrument_id": instrument.id,
        "pipeline": "full-validation-rolling-5y-phase-confidence-v2",
        "horizon": HORIZON,
        "training_years": args.training_years,
        "cost": 0.005,
        "folds": [],
        "started_at": started.isoformat(),
    }
    for year in test_years:
        fold_started = datetime.now(timezone.utc)
        fold = run_fold(rows, year, args.training_years, args.minimum_phase_samples)
        fold["duration_seconds"] = (datetime.now(timezone.utc) - fold_started).total_seconds()
        report["folds"].append(fold)
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False))
        print("FULL_FOLD", json.dumps({"symbol": args.symbol, "year": year, "confidence": fold["chosen_confidence"], "metrics": fold["metrics"], "profitable": fold["profitable"]}), flush=True)
    folds = report["folds"]
    profitable_folds = sum(bool(fold["profitable"]) for fold in folds)
    profit_factors = [effective_profit_factor(fold["metrics"]) for fold in folds]
    trades = sum(int(fold["metrics"]["independent_trades"]) for fold in folds)
    weighted_mean = sum(float(fold["metrics"]["independent_mean_return"] or 0) * int(fold["metrics"]["independent_trades"]) for fold in folds) / max(1, trades)
    latest = folds[-1]["metrics"] if folds else {}
    validated = (trades >= 5 and profitable_folds >= 2
                 and float(np.median(profit_factors)) >= 1.25 and weighted_mean > 0
                 and effective_profit_factor(latest) >= 1.10)
    evidence = "high" if trades >= 20 else "good" if trades >= 10 else "limited" if trades >= 5 else "experimental"
    report["summary"] = {
        "folds": len(folds), "profitable_folds": profitable_folds,
        "independent_trades": trades, "median_profit_factor": float(np.median(profit_factors)),
        "weighted_mean_return": weighted_mean,
        "evidence_class": evidence,
        "decision": "candidate_pending_context_filters" if validated else "documented_not_profitable",
    }
    report["duration_seconds"] = (datetime.now(timezone.utc) - started).total_seconds()
    report["completed_at"] = datetime.now(timezone.utc).isoformat()
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False))
    print("FULL_SUMMARY", json.dumps(report["summary"]), flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
