from __future__ import annotations

import argparse
from datetime import datetime, timezone
import json
from math import sqrt
from pathlib import Path
from statistics import fmean, pstdev

import numpy as np
import torch

import app.sector_deep_learning as sector_module
import scripts.train_sector_deep_learning as trainer
from app.cli.main import _import
from app.config.settings import settings
from app.database.connection import Database
from app.market.benchmark import resolve_benchmark_symbol
from app.repositories.instrument_repository import InstrumentRepository
from app.sector_deep_learning import SectorObservation, build_sector_samples


HORIZONS = (20, 60)
THREE_REGIMES = ("bull", "sideways", "stress")
FOUR_REGIMES = ("bull_calm", "bull_volatile", "sideways", "stress")
SIX_REGIMES = tuple(
    f"{trend}_{volatility}"
    for trend in ("bull", "sideways", "stress")
    for volatility in ("low_vol", "high_vol")
)


def daily_closes(bars) -> dict[datetime, float]:
    result = {}
    for bar in bars:
        stamp = bar.timestamp
        if stamp.tzinfo is not None:
            stamp = stamp.replace(tzinfo=None)
        stamp = stamp.replace(hour=0, minute=0, second=0, microsecond=0)
        if float(bar.close) > 0:
            result[stamp] = float(bar.close)
    return result


def observations(stock_bars, market_bars) -> list[SectorObservation]:
    stock = daily_closes(stock_bars)
    market = daily_closes(market_bars)
    dates = sorted(set(stock).intersection(market))
    output = []
    stock_prices = [stock[stamp] for stamp in dates]
    market_prices = [market[stamp] for stamp in dates]
    for index in range(200, len(dates)):
        def ret(values, days):
            return values[index] / values[index - days] - 1.0

        stock_returns = [stock_prices[i] / stock_prices[i - 1] - 1 for i in range(index - 19, index + 1)]
        market_returns = [market_prices[i] / market_prices[i - 1] - 1 for i in range(index - 19, index + 1)]
        stock_1, stock_5, stock_20 = ret(stock_prices, 1), ret(stock_prices, 5), ret(stock_prices, 20)
        market_1, market_5, market_20 = ret(market_prices, 1), ret(market_prices, 5), ret(market_prices, 20)
        features = (
            stock_1, stock_5, stock_20, ret(stock_prices, 60),
            pstdev(stock_returns) * sqrt(252),
            sum(value > 0 for value in stock_returns) / len(stock_returns),
            float(stock_prices[index] > fmean(stock_prices[index - 19:index + 1])),
            float(stock_prices[index] > fmean(stock_prices[index - 49:index + 1])),
            float(stock_prices[index] > fmean(stock_prices[index - 199:index + 1])),
            pstdev(stock_returns), 1.0,
            market_1, market_5, market_20, pstdev(market_returns) * sqrt(252),
            stock_1 - market_1, stock_5 - market_5, stock_20 - market_20,
        )
        output.append(SectorObservation(dates[index], stock_prices[index], features, 1, 1))
    return output


def regime(sample, volatility_threshold: float, scheme: str) -> str:
    market_return = float(sample.sequence[-1][13])
    market_volatility = float(sample.sequence[-1][14])
    trend = "stress" if market_return < -0.02 else "bull" if market_return > 0.02 else "sideways"
    if scheme == "six":
        volatility = "high_vol" if market_volatility > volatility_threshold else "low_vol"
        return f"{trend}_{volatility}"
    if trend == "stress":
        return "stress"
    if trend == "bull":
        if scheme == "three":
            return "bull"
        return "bull_volatile" if market_volatility > volatility_threshold else "bull_calm"
    return "sideways"


def combine(evaluations: list[dict]) -> dict:
    result = {}
    for horizon in HORIZONS:
        key = str(horizon)
        total = sum(int(value[key]["samples"]) for value in evaluations)
        result[key] = {
            name: sum(int(value[key]["samples"]) * float(value[key][name]) for value in evaluations) / total
            for name in ("direction_accuracy", "mae", "momentum_baseline_accuracy", "always_up_baseline_accuracy", "lift_vs_best_baseline")
        }
        result[key]["samples"] = total
    return result


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", default="7203.T")
    parser.add_argument("--benchmark", default="auto")
    parser.add_argument("--years", type=int, default=10)
    parser.add_argument("--sequence-length", type=int, default=60)
    parser.add_argument("--fold-epochs", type=int, default=12)
    parser.add_argument("--minimum-training-years", type=int, default=5)
    parser.add_argument("--minimum-regime-samples", type=int, default=150)
    parser.add_argument("--regime-scheme", choices=("three", "four", "six"), default="three")
    parser.add_argument("--volatility-quantile", type=float, default=0.50)
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--production-filter", action="store_true")
    parser.add_argument("--minimum-filter-accuracy", type=float, default=0.50)
    args = parser.parse_args()

    trainer.HORIZONS = HORIZONS
    sector_module.HORIZONS = HORIZONS
    regimes = THREE_REGIMES if args.regime_scheme == "three" else SIX_REGIMES if args.regime_scheme == "six" else FOUR_REGIMES
    with Database() as database:
        instrument = InstrumentRepository(database).find_by_symbol(args.symbol)
        if instrument is None:
            raise RuntimeError(f"Unknown instrument: {args.symbol}")
        benchmark = resolve_benchmark_symbol(instrument, args.benchmark)
        stock_bars = _import(database, args.symbol, "1d", args.years, persist=False).bars
        market_bars = _import(database, benchmark, "1d", args.years, persist=False).bars
    values = observations(stock_bars, market_bars)
    samples = build_sector_samples(args.symbol, values, sequence_length=args.sequence_length, horizons=HORIZONS)
    ids = {args.symbol: 0}
    years = sorted({sample.timestamp.year for sample in samples})
    folds = []
    for test_year in years[args.minimum_training_years:]:
        train = [sample for sample in samples if sample.timestamp.year < test_year]
        test = [sample for sample in samples if sample.timestamp.year == test_year]
        if not train or not test:
            continue
        if not 0.1 <= args.volatility_quantile <= 0.9:
            raise ValueError("volatility-quantile must be between 0.1 and 0.9")
        threshold = float(np.quantile(
            [float(sample.sequence[-1][14]) for sample in train],
            args.volatility_quantile,
        ))
        global_model, global_mean, global_std, _ = trainer._fit(train, ids, epochs=args.fold_epochs, seed=args.seed)
        global_metrics = trainer._evaluate(global_model, test, ids, global_mean, global_std)
        expert_evaluations = []
        regime_metrics_by_phase = {}
        counts = {}
        for offset, name in enumerate(regimes):
            regime_train = [sample for sample in train if regime(sample, threshold, args.regime_scheme) == name]
            regime_test = [sample for sample in test if regime(sample, threshold, args.regime_scheme) == name]
            counts[name] = {"train": len(regime_train), "test": len(regime_test), "dedicated_model": len(regime_train) >= args.minimum_regime_samples}
            if not regime_test:
                continue
            if len(regime_train) < args.minimum_regime_samples:
                evaluation = trainer._evaluate(global_model, regime_test, ids, global_mean, global_std)
            else:
                model, mean, std, _ = trainer._fit(regime_train, ids, epochs=args.fold_epochs, seed=args.seed + offset + 1)
                evaluation = trainer._evaluate(model, regime_test, ids, mean, std)
            expert_evaluations.append(evaluation)
            regime_metrics_by_phase[name] = evaluation
        folds.append({
            "test_year": test_year, "training_samples": len(train), "test_samples": len(test),
            "volatility_threshold": threshold, "regime_counts": counts,
            "global_metrics": global_metrics, "regime_metrics": combine(expert_evaluations),
            "regime_metrics_by_phase": regime_metrics_by_phase,
        })
        print(f"FOLD_COMPLETE {test_year} samples={len(test)}", flush=True)

    report = {
        "version": datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ"),
        "method": "single_stock_causal_market_regime_experts_vs_global_pytorch_gru",
        "symbol": args.symbol, "benchmark": benchmark, "seed": args.seed,
        "horizons": HORIZONS, "regime_scheme": args.regime_scheme,
        "regimes": regimes, "volatility_quantile": args.volatility_quantile,
        "years_requested": args.years,
        "observations": len(values), "samples": len(samples), "walk_forward": folds,
    }
    output_dir = settings.model_path / "experiments" / "single_stock_market_regimes"
    output_dir.mkdir(parents=True, exist_ok=True)
    output = output_dir / f"{args.symbol.replace('^', '')}_{report['version']}_seed{args.seed}.json"
    output.write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(f"REPORT {output}")

    if args.production_filter:
        pit_metrics = [
            phase[str(20)]
            for fold in folds
            for phase in fold["regime_metrics_by_phase"].values()
            if str(20) in phase
        ]
        evaluated = sum(int(value["samples"]) for value in pit_metrics)
        accuracy = (
            sum(int(value["samples"]) * float(value["direction_accuracy"]) for value in pit_metrics) / evaluated
            if evaluated else 0.0
        )
        enabled = evaluated >= 200 and accuracy >= args.minimum_filter_accuracy
        threshold = float(np.quantile(
            [float(sample.sequence[-1][14]) for sample in samples], args.volatility_quantile,
        ))
        experts = {}
        for offset, name in enumerate(regimes):
            phase_samples = [sample for sample in samples if regime(sample, threshold, args.regime_scheme) == name]
            dedicated = len(phase_samples) >= args.minimum_regime_samples
            training_samples = phase_samples if dedicated else samples
            model, mean, std, training_filter = trainer._fit(
                training_samples, ids, epochs=max(args.fold_epochs, 20), seed=args.seed + offset + 1,
            )
            experts[name] = {
                "state_dict": model.state_dict(), "normalization_mean": mean,
                "normalization_std": std, "samples": len(training_samples),
                "dedicated": dedicated, "training_filter": training_filter,
            }

        latest_sequence = tuple(item.features for item in values[-args.sequence_length:])
        latest_proxy = type("LatestSample", (), {"sequence": latest_sequence})()
        latest_phase = regime(latest_proxy, threshold, args.regime_scheme)
        expert = experts[latest_phase]
        sequence = np.asarray(latest_sequence, dtype=np.float32)
        mean = np.asarray(expert["normalization_mean"], dtype=np.float32)
        std = np.asarray(expert["normalization_std"], dtype=np.float32)
        std = np.where(std < 1e-8, 1.0, std)
        model = trainer.SectorGRU(sequence.shape[-1], 1)
        model.load_state_dict(expert["state_dict"])
        model.eval()
        with torch.no_grad():
            logits, _ = model(
                torch.from_numpy(((sequence - mean) / std)[None, :, :]),
                torch.tensor([0], dtype=torch.int64),
            )
        probability = float(torch.sigmoid(logits[0, HORIZONS.index(20)]))
        trained_at = datetime.now(timezone.utc)
        artifact_dir = settings.model_path / "phase_filters"
        artifact_dir.mkdir(parents=True, exist_ok=True)
        safe_symbol = args.symbol.replace("^", "").replace("/", "_")
        artifact = artifact_dir / f"{safe_symbol}_three_phase_20t.pt"
        torch.save({
            "model": "StockThreePhaseGRUFilter", "filter_only": True,
            "symbol": args.symbol, "instrument_id": instrument.id, "benchmark": benchmark,
            "horizons": HORIZONS, "regimes": regimes, "regime_scheme": args.regime_scheme,
            "sequence_length": args.sequence_length, "volatility_threshold": threshold,
            "experts": experts, "trained_at": trained_at.isoformat(),
            "quality_gate": {"enabled": enabled, "accuracy_20d": accuracy, "samples": evaluated,
                             "minimum_accuracy": args.minimum_filter_accuracy},
        }, artifact)
        portable_artifact = artifact.with_suffix(".npz")
        portable = {}
        for phase_name, phase_expert in experts.items():
            portable[f"{phase_name}__mean"] = np.asarray(phase_expert["normalization_mean"], dtype=np.float32)
            portable[f"{phase_name}__std"] = np.asarray(phase_expert["normalization_std"], dtype=np.float32)
            for state_name, tensor in phase_expert["state_dict"].items():
                portable[f"{phase_name}__{state_name}"] = tensor.detach().cpu().numpy()
        portable_meta = {
            "model": "StockThreePhaseGRUFilter", "portable_inference": "numpy_gru_v1",
            "filter_only": True, "symbol": args.symbol, "instrument_id": instrument.id,
            "benchmark": benchmark, "horizons": list(HORIZONS), "regimes": list(regimes),
            "regime_scheme": args.regime_scheme, "sequence_length": args.sequence_length,
            "volatility_threshold": threshold, "trained_at": trained_at.isoformat(),
            "quality_gate": {"enabled": enabled, "accuracy_20d": accuracy, "samples": evaluated,
                             "minimum_accuracy": args.minimum_filter_accuracy},
        }
        portable["metadata"] = np.asarray(json.dumps(portable_meta))
        np.savez_compressed(portable_artifact, **portable)
        meta = {
            "source": "pytorch_stock_three_phase_gru_20t", "context_only": True,
            "filter_only": True, "enabled": enabled, "phase": latest_phase,
            "probability_up": probability, "threshold": 0.5,
            "benchmark": benchmark, "artifact": portable_artifact.name,
            "quality_gate": {"accuracy_20d": accuracy, "samples": evaluated,
                             "minimum_accuracy": args.minimum_filter_accuracy},
        }
        with Database() as database:
            database.execute(
                """
                INSERT INTO market_context_predictions
                    (prediction_date, scope_type, scope_key, score, confidence,
                     signal, member_count, meta, created_at, updated_at)
                VALUES (%s,'stock_phase20',%s,%s,%s,%s,1,%s::jsonb,NOW(),NOW())
                ON CONFLICT (prediction_date, scope_type, scope_key)
                DO UPDATE SET score=EXCLUDED.score, confidence=EXCLUDED.confidence,
                    signal=EXCLUDED.signal, member_count=1, meta=EXCLUDED.meta, updated_at=NOW()
                """,
                (values[-1].timestamp.date(), str(instrument.id), probability * 10.0,
                 abs(probability - 0.5) * 200.0,
                 "BUY" if probability >= 0.5 else "SELL", json.dumps(meta)),
            )
            database.commit()
        print(
            f"PHASE_FILTER artifact={artifact} portable={portable_artifact} phase={latest_phase} p20={probability:.4f} "
            f"accuracy={accuracy:.4f} samples={evaluated} enabled={str(enabled).lower()}"
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
