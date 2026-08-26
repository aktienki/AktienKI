from __future__ import annotations

import argparse
from datetime import datetime, timezone
from math import prod
import json
from pathlib import Path

import numpy as np
import pandas as pd

from app.cli.main import _import, _training_models
from app.database.connection import Database
from app.dataset.dataset import Dataset, DatasetSplit
from app.dataset.sample_weights import RecencyWeightConfig, build_recency_weights
from app.dataset.scaler import StandardScaler
from app.dataset.splitter import TimeSplitConfig, split_indices
from app.dataset.target_builder import TargetConfig, build_targets
from app.features import FeatureBuilder
from app.features.profile import FeatureProfile, triple_daily_macro_profile
from app.market.benchmark import resolve_benchmark_symbol
from app.repositories.instrument_repository import InstrumentRepository
from app.training import TrainingEngine

from test_stock_regime_ensemble_experts import PHASES, bars_frame


HORIZONS = (5, 10, 15, 20)
SYMBOLS = ("GOOGL", "ASML.AS", "7203.T")


def align(target_bars, source_bars) -> list[float]:
    points = sorted((bar.timestamp.replace(tzinfo=None), float(bar.close)) for bar in source_bars)
    if not points:
        return [float("nan")] * len(target_bars)
    stamps, values = [item[0] for item in points], [item[1] for item in points]
    output, position = [], -1
    for bar in target_bars:
        stamp = bar.timestamp.replace(tzinfo=None)
        while position + 1 < len(stamps) and stamps[position + 1] <= stamp:
            position += 1
        output.append(values[position] if position >= 0 else float("nan"))
    return output


def write_report(path: Path, report: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    temporary.replace(path)


def raw_rows(bars, benchmark_bars, macro_bars, horizon: int, stock_only: bool = False) -> pd.DataFrame:
    profile = triple_daily_macro_profile()
    if stock_only:
        excluded = {"relative_strength", "rolling_correlation", "rolling_beta"}
        profile = FeatureProfile(
            name="stock_only_technical_v1",
            indicator_specs=tuple(spec for spec in profile.indicator_specs if spec.name not in excluded),
            drop_incomplete_rows=profile.drop_incomplete_rows,
            metadata={"ablation": "no_market_index_or_macro"},
        )
    features = FeatureBuilder().build(
        timestamps=[bar.timestamp for bar in bars], open=[float(bar.open) for bar in bars],
        high=[float(bar.high) for bar in bars], low=[float(bar.low) for bar in bars],
        close=[float(bar.close) for bar in bars], volume=[float(bar.volume or 0) for bar in bars],
        profile=profile,
        benchmark_close=None if stock_only else align(bars, benchmark_bars),
        macro_closes={} if stock_only else {name: align(bars, source) for name, source in macro_bars.items()},
    )
    names = sorted(features.columns)
    closes = [float(bar.close) for bar in bars]
    targets = build_targets(closes, TargetConfig(horizon=horizon))
    target_by_stamp = {bar.timestamp.replace(tzinfo=None): targets[index] for index, bar in enumerate(bars)}
    rows = []
    for index, stamp in enumerate(features.timestamps):
        normalized = stamp.replace(tzinfo=None)
        values = [features.columns[name][index] for name in names]
        target = target_by_stamp.get(normalized)
        if target is None or not np.isfinite(target) or any(value is None or not np.isfinite(value) for value in values):
            continue
        rows.append({"timestamp": normalized, "X": [float(value) for value in values], "y": float(target)})
    frame = pd.DataFrame(rows).set_index("timestamp")
    frame.attrs["feature_names"] = names
    return frame


def standard_train(
    pool: pd.DataFrame,
    horizon: int,
    feature_names: list[str],
    recency_half_life_years: float | None = None,
):
    config = TimeSplitConfig(train_ratio=.80, validation_ratio=.10, test_ratio=.10,
                             minimum_train_samples=80, minimum_validation_samples=10,
                             minimum_test_samples=10, purge_gap=horizon, embargo_gap=horizon)
    train_idx, validation_idx, test_idx = split_indices(len(pool), config)
    matrix = pool.X.tolist()
    scaler = StandardScaler(); scaler.fit([matrix[index] for index in train_idx])
    transformed = scaler.transform(matrix)
    if recency_half_life_years is not None:
        age_days = (pool.index.max() - pool.index).days.to_numpy(dtype=float)
        weights = np.power(0.5, age_days / (365.25 * recency_half_life_years)).tolist()
    else:
        weights = build_recency_weights(len(pool), RecencyWeightConfig())
    train_targets = [float(pool.y.iloc[index]) for index in train_idx]
    lower, upper = np.quantile(train_targets, [.01, .99])
    def split(indices, clip=False):
        return DatasetSplit(
            X=[transformed[index] for index in indices],
            y=[float(np.clip(pool.y.iloc[index], lower, upper)) if clip else float(pool.y.iloc[index]) for index in indices],
            timestamps=[pool.index[index].to_pydatetime() for index in indices],
            sample_weights=[weights[index] for index in indices],
        )
    dataset = Dataset(train=split(train_idx, True), validation=split(validation_idx), test=split(test_idx),
                      feature_names=feature_names, target_name=f"future_return_{horizon}", scaler=scaler)
    result = TrainingEngine().train(
        dataset=dataset, models=_training_models("horizon"), task="regression",
        strategy_holding_period=horizon, strategy_periods_per_year=max(1, 252 // horizon),
        transaction_cost=.005, minimum_absolute_prediction=.01, position_side="long",
    )
    refit_rows = TrainingEngine.refit_on_all_labeled_data(dataset, [result.champion])
    return result.champion, scaler, len(result.evaluations), refit_rows


def evaluate(actual: np.ndarray, predicted: np.ndarray, horizon: int = 20) -> dict:
    selected = actual[predicted >= .01] - .005
    wins, losses = selected[selected > 0], selected[selected < 0]
    # A horizon forecast is emitted on every trading day. Treating all of
    # those rows as separate trades materially overstates the evidence because
    # adjacent 20T outcomes share almost their entire holding period. Keep the
    # legacy row metrics for comparability, but add a conservative execution
    # view that cannot open a new position until the previous horizon elapsed.
    independent = []
    next_entry = 0
    for index, (realized, forecast) in enumerate(zip(actual, predicted)):
        if index < next_entry or forecast < .01:
            continue
        independent.append(float(realized) - .005)
        next_entry = index + horizon
    independent = np.asarray(independent, dtype=float)
    independent_wins = independent[independent > 0]
    independent_losses = independent[independent < 0]
    return {
        "samples": len(actual), "direction_accuracy": float(((actual >= 0) == (predicted >= 0)).mean()),
        "mae": float(np.abs(actual - predicted).mean()), "trades": len(selected),
        "win_rate": float((selected > 0).mean()) if len(selected) else None,
        "profit_factor": float(wins.sum() / abs(losses.sum())) if len(losses) and losses.sum() else None,
        "total_return": float(prod(1 + selected) - 1) if len(selected) else 0.0,
        "independent_trades": len(independent),
        "independent_win_rate": float((independent > 0).mean()) if len(independent) else None,
        "independent_profit_factor": (
            float(independent_wins.sum() / abs(independent_losses.sum()))
            if len(independent_losses) and independent_losses.sum() else None
        ),
        "independent_mean_return": float(independent.mean()) if len(independent) else None,
        "independent_total_return": float(prod(1 + independent) - 1) if len(independent) else 0.0,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbols", nargs="*", default=list(SYMBOLS))
    parser.add_argument("--index-symbol")
    parser.add_argument("--horizons", nargs="*", type=int, choices=HORIZONS, default=list(HORIZONS))
    parser.add_argument("--years", type=int, default=10)
    parser.add_argument("--rolling-training-years", type=int)
    parser.add_argument("--stock-only", action="store_true")
    parser.add_argument("--no-phase-fallback", action="store_true",
                        help="Abstain instead of using the global model when a phase has insufficient evidence")
    parser.add_argument("--minimum-phase-samples", type=int, default=150)
    parser.add_argument("--test-years", nargs="*", type=int, default=[2024, 2025, 2026])
    parser.add_argument("--output", type=Path, default=Path("reports/standard-horizon-regime-experts-3stocks.json"))
    parser.add_argument("--resume", action="store_true")
    args = parser.parse_args()
    report = {"generated_at": datetime.now(timezone.utc).isoformat(), "symbols": {},
              "standard_procedure": {"feature_profile": "triple_daily_macro_v1", "models": 8,
              "split": "80/10/10", "purge_and_embargo": "equal_to_horizon", "transaction_cost": .005,
              "phase_definition_version": "indicator-regime-v1",
              "phase_selector": "benchmark close/SMA50/SMA200 + EMA20 slope10 + DI14",
              "pytorch_role": "post_filter_only"}}
    if args.resume and args.output.is_file():
        existing = json.loads(args.output.read_text(encoding="utf-8"))
        if existing.get("standard_procedure", {}).get("phase_definition_version") == "indicator-regime-v1":
            report = existing
    with Database() as database:
        repository = InstrumentRepository(database)
        if args.index_symbol:
            members = database.fetch_all(
                """SELECT instrument.symbol FROM index_memberships membership
                   JOIN market_indices market_index ON market_index.id=membership.market_index_id
                   JOIN instruments instrument ON instrument.id=membership.instrument_id
                   WHERE UPPER(market_index.symbol)=UPPER(%s)
                     AND membership.removed_at IS NULL AND instrument.deleted_at IS NULL
                   ORDER BY instrument.symbol""",
                (args.index_symbol,),
            )
            args.symbols = [str(row["symbol"]) for row in members]
            if not args.symbols:
                raise RuntimeError(f"No active members found for {args.index_symbol}")
        agg, us2y = [], []
        if not args.stock_only:
            agg = list(_import(database, "AGG", "1d", args.years, persist=False).bars)
            us2y_instrument = repository.find_by_symbol("US2Y")
            if us2y_instrument is None or us2y_instrument.id is None:
                raise RuntimeError("US2Y instrument missing")
            us2y_rows = database.fetch_all("SELECT bar_time, open, high, low, close, volume FROM price_bars WHERE instrument_id=%s AND interval='1d' ORDER BY bar_time", (us2y_instrument.id,))
            from app.models.instrument_market_data import InstrumentMarketData
            us2y = [InstrumentMarketData(instrument_id=us2y_instrument.id,timeframe="1d",timestamp=row["bar_time"],open=row["open"],high=row["high"],low=row["low"],close=row["close"],volume=int(row["volume"] or 0)) for row in us2y_rows]
        for symbol in args.symbols:
            if args.resume and symbol in report["symbols"]:
                print(f"RESUME_SKIP symbol={symbol}", flush=True)
                continue
            instrument = repository.find_by_symbol(symbol)
            if instrument is None:
                raise RuntimeError(f"Unknown instrument {symbol}")
            benchmark = None if args.stock_only else resolve_benchmark_symbol(instrument, "auto")
            bars = list(_import(database, symbol, "1d", args.years, persist=False).bars)
            benchmark_bars = bars if args.stock_only else list(_import(database, benchmark, "1d", args.years, persist=False).bars)
            market_phase_frame = bars_frame(bars if args.stock_only else benchmark_bars, "stock_phase" if args.stock_only else "market")
            phase = market_phase_frame.attrs["phase"]
            symbol_report = {"benchmark": benchmark, "folds": []}
            for horizon in args.horizons:
                rows = raw_rows(bars, benchmark_bars, {"AGG": agg, "US2Y": us2y}, horizon, args.stock_only)
                feature_names = rows.attrs["feature_names"]
                rows.attrs.clear(); rows["phase"] = phase.reindex(rows.index)
                rows = rows.dropna(subset=["phase"])
                for year in args.test_years:
                    start = pd.Timestamp(f"{year}-01-01")
                    pool = rows[rows.index < start - pd.offsets.BDay(horizon)]
                    if args.rolling_training_years:
                        pool = pool[pool.index >= start - pd.DateOffset(years=args.rolling_training_years)]
                    test = rows[rows.index.year == year]
                    if len(pool) < 500 or len(test) < 20:
                        continue
                    global_champion, global_scaler, model_count, global_refit_rows = standard_train(pool, horizon, feature_names)
                    global_prediction = np.asarray(global_champion.model.predict(global_scaler.transform(test.X.tolist())))
                    routed = (np.full(len(test), -1.0) if args.no_phase_fallback else np.empty(len(test)))
                    champions = {}; fallback = []
                    for phase_name in PHASES:
                        mask = test.phase == phase_name; phase_pool = pool[pool.phase == phase_name]
                        if not mask.any():
                            continue
                        if len(phase_pool) < args.minimum_phase_samples:
                            if not args.no_phase_fallback:
                                routed[mask.to_numpy()] = global_prediction[mask.to_numpy()]
                            fallback.append(phase_name)
                        else:
                            try:
                                champion, scaler, count, refit_rows = standard_train(phase_pool, horizon, feature_names)
                            except Exception as exception:
                                # A phase can satisfy the coarse sample threshold but
                                # still be too small after purge/embargo for the exact
                                # production 80/10/10 split. Never weaken that split:
                                # route this phase through the global standard model.
                                if not args.no_phase_fallback:
                                    routed[mask.to_numpy()] = global_prediction[mask.to_numpy()]
                                fallback.append(phase_name)
                                champions[phase_name] = {
                                    "fallback": "global_standard_model",
                                    "reason": type(exception).__name__,
                                    "phase_samples": len(phase_pool),
                                }
                            else:
                                routed[mask.to_numpy()] = champion.model.predict(scaler.transform(test.loc[mask, "X"].tolist()))
                                champions[phase_name] = {"model": champion.model_name, "refit_rows": refit_rows}
                    fold = {"horizon": horizon, "year": year, "test_samples": len(test), "model_candidates": model_count,
                            "global_champion": global_champion.model_name, "global_refit_rows": global_refit_rows,
                            "phase_champions": champions,
                            "fallback_phases": fallback, "global": evaluate(test.y.to_numpy(), global_prediction, horizon),
                            "phase_fallback_policy": "abstain" if args.no_phase_fallback else "global_standard_model",
                            "regime_experts": evaluate(test.y.to_numpy(), routed, horizon)}
                    symbol_report["folds"].append(fold)
                    print(f"STANDARD_FOLD symbol={symbol} horizon={horizon} year={year} champions={champions} fallback={fallback}", flush=True)
            report["symbols"][symbol] = symbol_report
            write_report(args.output, report)
    for symbol, item in report["symbols"].items():
        item["summary"] = {}
        for horizon in args.horizons:
            folds = [fold for fold in item["folds"] if fold["horizon"] == horizon]
            item["summary"][str(horizon)] = {}
            if not folds:
                continue
            for route in ("global", "regime_experts"):
                weights = [fold[route]["samples"] for fold in folds]
                item["summary"][str(horizon)][route] = {
                    metric: float(np.average([fold[route][metric] for fold in folds], weights=weights))
                    for metric in ("direction_accuracy", "mae")
                }
            item["summary"][str(horizon)]["accuracy_delta"] = item["summary"][str(horizon)]["regime_experts"]["direction_accuracy"] - item["summary"][str(horizon)]["global"]["direction_accuracy"]
    write_report(args.output, report)
    print(f"REPORT {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
