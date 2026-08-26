from __future__ import annotations

import argparse
from dataclasses import asdict
from datetime import datetime, timezone
import json
from pathlib import Path
import random
from statistics import fmean

import numpy as np
import torch
from torch import nn
from torch.utils.data import DataLoader, TensorDataset

from app.cli.main import _import
from app.config.settings import settings
from app.database.connection import Database
from app.repositories.instrument_repository import InstrumentRepository
from app.sector_deep_learning import (
    FEATURE_NAMES,
    HORIZONS,
    add_cross_sector_context,
    aggregate_sector_history,
    build_sector_samples,
    exclude_extreme_volatility_training_samples,
    relative_sector_targets,
)


class SectorGRU(nn.Module):
    def __init__(self, feature_count: int, sector_count: int, hidden_size: int = 48):
        super().__init__()
        self.sector_embedding = nn.Embedding(sector_count, 8)
        self.temporal = nn.Sequential(
            nn.Conv1d(feature_count, 32, kernel_size=5, padding=2), nn.GELU(),
            nn.Conv1d(32, 32, kernel_size=3, padding=1), nn.GELU(),
        )
        self.gru = nn.GRU(32 + 8, hidden_size, batch_first=True)
        self.head = nn.Sequential(
            nn.LayerNorm(hidden_size), nn.Linear(hidden_size, 32), nn.GELU(),
            nn.Dropout(0.10), nn.Linear(32, len(HORIZONS) * 2),
        )

    def forward(self, sequence, sector_id):
        temporal = self.temporal(sequence.transpose(1, 2)).transpose(1, 2)
        embedded = self.sector_embedding(sector_id)[:, None, :].expand(
            -1, sequence.shape[1], -1
        )
        _, hidden = self.gru(torch.cat((temporal, embedded), dim=-1))
        output = self.head(hidden[-1])
        return output[:, :len(HORIZONS)], output[:, len(HORIZONS):]


def _tensors(samples, sector_ids, mean=None, std=None):
    X = np.asarray([sample.sequence for sample in samples], dtype=np.float32)
    y = np.asarray([sample.future_returns for sample in samples], dtype=np.float32)
    ids = np.asarray([sector_ids[sample.sector] for sample in samples], dtype=np.int64)
    if mean is None:
        mean = X.reshape(-1, X.shape[-1]).mean(axis=0)
        std = X.reshape(-1, X.shape[-1]).std(axis=0)
    std = np.where(std < 1e-8, 1.0, std)
    return (
        torch.from_numpy((X - mean) / std), torch.from_numpy(ids),
        torch.from_numpy(y), mean, std,
    )


def _fit(train_samples, sector_ids, *, epochs, seed, patience=4):
    torch.manual_seed(seed)
    random.seed(seed)
    filtered, volatility_threshold = exclude_extreme_volatility_training_samples(
        train_samples
    )
    ordered = sorted(filtered, key=lambda sample: sample.timestamp)
    split = max(1, int(len(ordered) * .85))
    fitting = ordered[:split]
    validation = ordered[split:] or ordered[-1:]
    X, ids, y, mean, std = _tensors(fitting, sector_ids)
    val_X, val_ids, val_y, _, _ = _tensors(validation, sector_ids, mean, std)
    model = SectorGRU(X.shape[-1], len(sector_ids))
    optimizer = torch.optim.AdamW(model.parameters(), lr=8e-4, weight_decay=1e-4)
    loader = DataLoader(TensorDataset(X, ids, y), batch_size=128, shuffle=True)
    positive = (y > 0).float().sum(dim=0)
    negative = y.shape[0] - positive
    positive_weight = torch.clamp(negative / torch.clamp(positive, min=1), .5, 2.0)
    best_loss = float("inf")
    best_state = None
    stale = 0
    for _ in range(epochs):
        model.train()
        for batch_X, batch_ids, batch_y in loader:
            logits, returns = model(batch_X, batch_ids)
            direction = (batch_y > 0).float()
            loss = nn.functional.binary_cross_entropy_with_logits(
                logits, direction, pos_weight=positive_weight
            )
            loss = loss + 8.0 * nn.functional.smooth_l1_loss(returns, batch_y)
            optimizer.zero_grad()
            loss.backward()
            nn.utils.clip_grad_norm_(model.parameters(), 1.0)
            optimizer.step()
        model.eval()
        with torch.no_grad():
            val_logits, val_returns = model(val_X, val_ids)
            val_direction = (val_y > 0).float()
            val_loss = nn.functional.binary_cross_entropy_with_logits(
                val_logits, val_direction, pos_weight=positive_weight
            ) + 8.0 * nn.functional.smooth_l1_loss(val_returns, val_y)
        if float(val_loss) < best_loss - 1e-4:
            best_loss = float(val_loss)
            best_state = {key: value.detach().clone() for key, value in model.state_dict().items()}
            stale = 0
        else:
            stale += 1
            if stale >= patience:
                break
    if best_state is not None:
        model.load_state_dict(best_state)
    return model, mean, std, {
        "samples_before_volatility_filter": len(train_samples),
        "samples_after_volatility_filter": len(filtered),
        "market_volatility_threshold": volatility_threshold,
    }


def _evaluate(model, samples, sector_ids, mean, std):
    X, ids, y, _, _ = _tensors(samples, sector_ids, mean, std)
    model.eval()
    with torch.no_grad():
        logits, returns = model(X, ids)
    probability = torch.sigmoid(logits).numpy()
    truth = y.numpy()
    predicted = returns.numpy()
    output = {
        str(horizon): {
            "direction_accuracy": float(((probability[:, i] >= .5) == (truth[:, i] > 0)).mean()),
            "mae": float(np.abs(predicted[:, i] - truth[:, i]).mean()),
            "samples": len(samples),
        }
        for i, horizon in enumerate(HORIZONS)
    }
    output["_point_in_time"] = []
    for row_index, sample in enumerate(samples):
        point = {"sector": sample.sector, "date": sample.timestamp.date().isoformat()}
        for index, horizon in enumerate(HORIZONS):
            point[f"probability_{horizon}d"] = float(probability[row_index, index])
            point[f"direction_{horizon}d"] = int(probability[row_index, index] >= .5)
        output["_point_in_time"].append(point)
    for index, horizon in enumerate(HORIZONS):
        momentum = np.asarray([
            sample.sequence[-1][2] >= 0 for sample in samples
        ], dtype=bool)
        always_up = np.ones(len(samples), dtype=bool)
        actual = truth[:, index] > 0
        output[str(horizon)]["momentum_baseline_accuracy"] = float((momentum == actual).mean())
        output[str(horizon)]["always_up_baseline_accuracy"] = float((always_up == actual).mean())
        output[str(horizon)]["lift_vs_best_baseline"] = float(
            output[str(horizon)]["direction_accuracy"]
            - max(output[str(horizon)]["momentum_baseline_accuracy"], output[str(horizon)]["always_up_baseline_accuracy"])
        )
    if 20 in HORIZONS:
        short_index = HORIZONS.index(20)
        short_direction = probability[:, short_index] >= .5
        short_truth = truth[:, short_index] > 0
        long_directions = {}
        for long_horizon in (40, 60):
            if long_horizon not in HORIZONS:
                continue
            long_direction = probability[:, HORIZONS.index(long_horizon)] >= .5
            long_directions[long_horizon] = long_direction
            aligned = short_direction == long_direction
            output["20"][f"accuracy_when_20d_{long_horizon}d_aligned"] = float(
                (short_direction[aligned] == short_truth[aligned]).mean()
            ) if aligned.any() else None
            output["20"][f"samples_when_20d_{long_horizon}d_aligned"] = int(aligned.sum())
        if 40 in long_directions and 60 in long_directions:
            all_aligned = (
                (short_direction == long_directions[40])
                & (short_direction == long_directions[60])
            )
            output["20"]["accuracy_when_20d_40d_60d_aligned"] = float(
                (short_direction[all_aligned] == short_truth[all_aligned]).mean()
            ) if all_aligned.any() else None
            output["20"]["samples_when_20d_40d_60d_aligned"] = int(all_aligned.sum())
    return output


def main() -> int:
    parser = argparse.ArgumentParser(description="Local aggregated sector GRU experiment")
    parser.add_argument("--years", type=int, default=10)
    parser.add_argument("--minimum-members", type=int, default=5)
    parser.add_argument("--sequence-length", type=int, default=60)
    parser.add_argument("--epochs", type=int, default=25)
    parser.add_argument("--fold-epochs", type=int, default=8)
    parser.add_argument("--minimum-training-years", type=int, default=5)
    parser.add_argument("--sector", action="append", dest="sectors")
    parser.add_argument("--max-members-per-sector", type=int)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()

    with Database() as database:
        repository = InstrumentRepository(database)
        active = [item for item in repository.find_active() if item.asset_type == "stock" and item.sector]
        sector_names = args.sectors or sorted({item.sector for item in active})
        all_samples = []
        sector_report = {}
        sector_observations = {}
        for sector in sector_names:
            members = [item for item in active if item.sector == sector]
            if args.max_members_per_sector:
                members = members[:args.max_members_per_sector]
            histories = {}
            for member in members:
                try:
                    histories[member.symbol] = list(_import(
                        database, member.symbol, "1d", args.years, persist=False
                    ).bars)
                except Exception as exc:
                    print(f"SECTOR_DATA_SKIPPED {sector} {member.symbol} {exc}", flush=True)
            observations = aggregate_sector_history(
                histories, minimum_members=args.minimum_members
            )
            if not observations:
                print(f"SECTOR_SKIPPED {sector} members={len(histories)}", flush=True)
                continue
            sector_observations[sector] = observations
            sector_report[sector] = {
                "members": sorted(histories), "observations": len(observations),
            }
            print(f"SECTOR_DATA_READY {sector} members={len(histories)} observations={len(observations)}", flush=True)

    sector_observations = add_cross_sector_context(sector_observations)
    for sector, observations in sector_observations.items():
        samples = build_sector_samples(sector, observations, sequence_length=args.sequence_length)
        all_samples.extend(samples)
        sector_report[sector].update({
            "samples": len(samples), "first": samples[0].timestamp.isoformat(),
            "last": samples[-1].timestamp.isoformat(),
        })
        print(f"SECTOR_READY {sector} samples={len(samples)}", flush=True)

    all_samples = relative_sector_targets(all_samples)

    if not all_samples:
        raise RuntimeError("No eligible sector samples")
    sector_ids = {sector: index for index, sector in enumerate(sorted(sector_report))}
    years = sorted({sample.timestamp.year for sample in all_samples})
    folds = []
    for test_year in years[args.minimum_training_years:]:
        train = [sample for sample in all_samples if sample.timestamp.year < test_year]
        test = [sample for sample in all_samples if sample.timestamp.year == test_year]
        if not train or not test:
            continue
        model, mean, std, training_filter = _fit(
            train, sector_ids, epochs=args.fold_epochs, seed=args.seed
        )
        metrics = _evaluate(model, test, sector_ids, mean, std)
        folds.append({"test_year": test_year, "training_samples": len(train), "test_samples": len(test), "training_filter": training_filter, "metrics": metrics})
        print(f"WALK_FORWARD_COMPLETE {test_year} samples={len(test)}", flush=True)

    model, mean, std, final_training_filter = _fit(
        all_samples, sector_ids, epochs=args.epochs, seed=args.seed
    )
    trained_at = datetime.now(timezone.utc)
    version = trained_at.strftime("%Y%m%dT%H%M%SZ")
    output_dir = settings.model_path / "experiments" / "sector_deep_learning"
    output_dir.mkdir(parents=True, exist_ok=True)
    artifact_path = output_dir / f"sector_gru_{version}.pt"
    torch.save({
        "state_dict": model.state_dict(), "feature_names": FEATURE_NAMES,
        "horizons": HORIZONS, "sector_ids": sector_ids,
        "normalization_mean": mean, "normalization_std": std,
        "sequence_length": args.sequence_length, "trained_at": trained_at.isoformat(),
        "model": "SectorGRU", "local_experiment": True,
        "training_filter": final_training_filter,
    }, artifact_path)
    report = {
        "version": version, "trained_at": trained_at.isoformat(),
        "method": "equal_weighted_sector_index_shared_tcn_gru_relative_strength",
        "target": "sector_return_minus_cross_sector_average_return",
        "local_only": True, "years_requested": args.years,
        "survivorship_bias_warning": "Uses current database sector membership.",
        "feature_names": FEATURE_NAMES, "horizons": HORIZONS,
        "extreme_volatility_training_filter": final_training_filter,
        "sectors": sector_report, "walk_forward": folds,
        "artifact": str(artifact_path),
        "mean_direction_accuracy": {
            str(horizon): fmean(fold["metrics"][str(horizon)]["direction_accuracy"] for fold in folds)
            for horizon in HORIZONS
        } if folds else {},
        "mean_baseline_accuracy": {
            str(horizon): {
                "momentum": fmean(fold["metrics"][str(horizon)]["momentum_baseline_accuracy"] for fold in folds),
                "always_up": fmean(fold["metrics"][str(horizon)]["always_up_baseline_accuracy"] for fold in folds),
                "model_lift": fmean(fold["metrics"][str(horizon)]["lift_vs_best_baseline"] for fold in folds),
            }
            for horizon in HORIZONS
        } if folds else {},
    }
    report_path = output_dir / f"sector_gru_{version}.json"
    report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"ARTIFACT {artifact_path}")
    print(f"REPORT {report_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
