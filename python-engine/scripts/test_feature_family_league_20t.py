from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd

from app.cli.main import _import
from app.database.connection import Database
from app.features import FeatureBuilder
from app.features.profile import FeatureProfile, triple_daily_macro_profile
from app.dataset.target_builder import TargetConfig, build_targets
from app.market.benchmark import resolve_benchmark_symbol
from app.models.instrument_market_data import InstrumentMarketData
from app.repositories.instrument_repository import InstrumentRepository

from test_standard_horizon_regime_experts_3stocks import align, evaluate, standard_train
from test_stock_regime_ensemble_experts import PHASES, bars_frame


HORIZON = 20
FAMILIES = {
    "trend": {"sma", "ema", "adx", "supertrend", "donchian", "market_regime"},
    "momentum": {"rsi", "stochastic", "williams_r", "macd", "roc", "cci"},
    "volatility": {"atr", "bollinger", "keltner", "rolling_volatility", "drawdown"},
    "volume": {"mfi", "cmf", "obv", "vwap", "relative_volume"},
    "patterns": {"ichimoku", "chart_patterns", "pivot_points", "rolling_zscore", "percentile_rank"},
    "relative_strength": {"relative_strength", "rolling_correlation", "rolling_beta"},
}
STAGES = (
    ("trend", ("trend",)),
    ("trend_momentum", ("trend", "momentum")),
    ("trend_momentum_volatility", ("trend", "momentum", "volatility")),
    ("technical_plus_volume", ("trend", "momentum", "volatility", "volume")),
    ("technical_all", ("trend", "momentum", "volatility", "volume", "patterns")),
    ("technical_relative", ("trend", "momentum", "volatility", "volume", "patterns", "relative_strength")),
    ("full_with_macro", ("trend", "momentum", "volatility", "volume", "patterns", "relative_strength")),
)


def profile_for(stage: str, family_names: tuple[str, ...]) -> FeatureProfile:
    base = triple_daily_macro_profile()
    allowed = set().union(*(FAMILIES[name] for name in family_names))
    specs = tuple(spec for spec in base.indicator_specs if spec.name in allowed)
    return FeatureProfile(
        name=f"feature_family_league_{stage}_v1",
        indicator_specs=specs,
        drop_incomplete_rows=True,
        metadata={"families": list(family_names), "stage": stage},
    )


def rows_for_profile(bars, benchmark_bars, macro_bars, profile: FeatureProfile) -> pd.DataFrame:
    needs_benchmark = any(spec.name in FAMILIES["relative_strength"] for spec in profile.indicator_specs)
    features = FeatureBuilder().build(
        timestamps=[bar.timestamp for bar in bars],
        open=[float(bar.open) for bar in bars],
        high=[float(bar.high) for bar in bars],
        low=[float(bar.low) for bar in bars],
        close=[float(bar.close) for bar in bars],
        volume=[float(bar.volume or 0) for bar in bars],
        profile=profile,
        benchmark_close=align(bars, benchmark_bars) if needs_benchmark else None,
        macro_closes={name: align(bars, source) for name, source in macro_bars.items()},
    )
    names = sorted(features.columns)
    targets = build_targets([float(bar.close) for bar in bars], TargetConfig(horizon=HORIZON))
    target_by_stamp = {bar.timestamp.replace(tzinfo=None): targets[index] for index, bar in enumerate(bars)}
    rows = []
    for index, stamp in enumerate(features.timestamps):
        stamp = stamp.replace(tzinfo=None)
        values = [features.columns[name][index] for name in names]
        target = target_by_stamp.get(stamp)
        if target is None or not np.isfinite(target) or any(value is None or not np.isfinite(value) for value in values):
            continue
        rows.append({"timestamp": stamp, "X": [float(value) for value in values], "y": float(target)})
    frame = pd.DataFrame(rows).set_index("timestamp")
    frame.attrs["feature_names"] = names
    return frame


def route(pool, test, names, minimum_phase_samples, half_life):
    global_champion, global_scaler, candidates, _ = standard_train(pool, HORIZON, names, half_life)
    global_prediction = np.asarray(global_champion.model.predict(global_scaler.transform(test.X.tolist())))
    routed = global_prediction.copy()
    phase_models, fallback = {}, []
    for phase in PHASES:
        mask = test.phase == phase
        if not mask.any():
            continue
        phase_pool = pool[pool.phase == phase]
        if len(phase_pool) < minimum_phase_samples:
            fallback.append(phase)
            continue
        try:
            champion, scaler, _, _ = standard_train(phase_pool, HORIZON, names, half_life)
        except Exception as error:
            fallback.append(phase)
            phase_models[phase] = {"fallback": type(error).__name__, "samples": len(phase_pool)}
            continue
        routed[mask.to_numpy()] = champion.model.predict(scaler.transform(test.loc[mask, "X"].tolist()))
        phase_models[phase] = {"model": champion.model_name, "samples": len(phase_pool)}
    return {
        "model_candidates": candidates,
        "global_champion": global_champion.model_name,
        "phase_models": phase_models,
        "fallback_phases": fallback,
        "global": evaluate(test.y.to_numpy(), global_prediction, HORIZON),
        "phase_routed": evaluate(test.y.to_numpy(), routed, HORIZON),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", default="ACN")
    parser.add_argument("--years", type=int, default=7)
    parser.add_argument("--training-years", type=int, default=5)
    parser.add_argument("--test-year", type=int, default=2025)
    parser.add_argument("--minimum-phase-samples", type=int, default=150)
    parser.add_argument("--recency-half-life-years", type=float, default=3.0)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    started = datetime.now(timezone.utc)
    with Database() as database:
        repository = InstrumentRepository(database)
        instrument = repository.find_by_symbol(args.symbol)
        benchmark = resolve_benchmark_symbol(instrument, "auto")
        bars = list(_import(database, args.symbol, "1d", args.years, persist=False).bars)
        benchmark_bars = list(_import(database, benchmark, "1d", args.years, persist=False).bars)
        agg = list(_import(database, "AGG", "1d", args.years, persist=False).bars)
        us2y_instrument = repository.find_by_symbol("US2Y")
        us2y_rows = database.fetch_all(
            "SELECT bar_time,open,high,low,close,volume FROM price_bars WHERE instrument_id=%s AND interval='1d' ORDER BY bar_time",
            (us2y_instrument.id,),
        )
        us2y = [InstrumentMarketData(instrument_id=us2y_instrument.id, timeframe="1d", timestamp=row["bar_time"],
                 open=row["open"], high=row["high"], low=row["low"], close=row["close"],
                 volume=int(row["volume"] or 0)) for row in us2y_rows]
    phase = bars_frame(bars, "stock_phase").attrs["phase"]
    report = {
        "symbol": args.symbol,
        "benchmark": benchmark,
        "horizon": HORIZON,
        "training_years": args.training_years,
        "test_year": args.test_year,
        "method": "nested_feature_family_league_with_stock_phase_challenger",
        "cost": 0.005,
        "unsupported_requested_features": ["stochastic_rsi", "ppo", "trix", "market_breadth", "vix", "us10y", "eurusd", "gold", "oil", "dollar_index", "pmi", "inflation", "labour_market", "model_meta_features"],
        "stages": [],
        "started_at": started.isoformat(),
    }
    test_start = pd.Timestamp(f"{args.test_year}-01-01")
    for stage, family_names in STAGES:
        stage_started = datetime.now(timezone.utc)
        macro = {"AGG": agg, "US2Y": us2y} if stage == "full_with_macro" else {}
        frame = rows_for_profile(bars, benchmark_bars, macro, profile_for(stage, family_names))
        names = frame.attrs["feature_names"]
        frame.attrs.clear()
        frame["phase"] = phase.reindex(frame.index)
        frame = frame.dropna(subset=["phase"])
        pool = frame[(frame.index >= test_start - pd.DateOffset(years=args.training_years)) &
                     (frame.index < test_start - pd.offsets.BDay(HORIZON))]
        test = frame[frame.index.year == args.test_year]
        result = route(pool, test, names, args.minimum_phase_samples, args.recency_half_life_years)
        result.update({
            "stage": stage,
            "families": list(family_names),
            "feature_count": len(names),
            "training_samples": len(pool),
            "test_samples": len(test),
            "duration_seconds": (datetime.now(timezone.utc) - stage_started).total_seconds(),
        })
        report["stages"].append(result)
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False))
        print("FAMILY_STAGE", stage, "features", len(names), "global_pf", result["global"]["independent_profit_factor"], "phase_pf", result["phase_routed"]["independent_profit_factor"], flush=True)
    report["duration_seconds"] = (datetime.now(timezone.utc) - started).total_seconds()
    report["completed_at"] = datetime.now(timezone.utc).isoformat()
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False))
    print("REPORT", args.output, flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
