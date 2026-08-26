from __future__ import annotations

import argparse
import json
from datetime import datetime, timedelta, timezone
from math import erf, sqrt
from pathlib import Path

import numpy as np

from app.database.connection import Database
from app.repositories.instrument_repository import InstrumentRepository
from app.repositories.market_data_repository import MarketDataRepository
from app.sector_deep_learning import add_cross_sector_context, aggregate_sector_history


HORIZON = 60
HORIZON_MINUTES = HORIZON * 1440
STANDARD_HORIZON_MINUTES = (7200, 14400, 21600, 28800)


def weighted_metric(report: dict, key: str) -> float:
    rows = [fold["metrics"][str(HORIZON)] for fold in report.get("walk_forward", [])]
    samples = sum(int(row["samples"]) for row in rows)
    if samples == 0:
        return 0.0
    return sum(float(row[key]) * int(row["samples"]) for row in rows) / samples


def sigmoid(values: np.ndarray) -> np.ndarray:
    return 1.0 / (1.0 + np.exp(-np.clip(values, -40.0, 40.0)))


def gelu(values: np.ndarray) -> np.ndarray:
    return 0.5 * values * (1.0 + np.vectorize(erf)(values / sqrt(2.0)))


def conv1d_same(sequence: np.ndarray, weight: np.ndarray, bias: np.ndarray) -> np.ndarray:
    channels, length = sequence.shape
    output_channels, _, kernel = weight.shape
    padded = np.pad(sequence, ((0, 0), (kernel // 2, kernel // 2)))
    output = np.empty((output_channels, length), dtype=np.float32)
    for index in range(length):
        output[:, index] = np.einsum(
            "ock,ck->o", weight, padded[:, index:index + kernel]
        ) + bias
    return output


def numpy_forward(sequence: np.ndarray, sector_id: int, values) -> tuple[np.ndarray, np.ndarray]:
    state = lambda name: np.asarray(values[f"state__{name}"], dtype=np.float32)
    mean = np.asarray(values["normalization_mean"], dtype=np.float32)
    std = np.asarray(values["normalization_std"], dtype=np.float32)
    std = np.where(std < 1e-8, 1.0, std)
    temporal = ((sequence - mean) / std).T
    temporal = gelu(conv1d_same(temporal, state("temporal.0.weight"), state("temporal.0.bias")))
    temporal = gelu(conv1d_same(temporal, state("temporal.2.weight"), state("temporal.2.bias"))).T
    embedding = state("sector_embedding.weight")[sector_id]
    inputs = np.concatenate((temporal, np.repeat(embedding[None, :], len(temporal), axis=0)), axis=1)
    weight_ih, weight_hh = state("gru.weight_ih_l0"), state("gru.weight_hh_l0")
    bias_ih, bias_hh = state("gru.bias_ih_l0"), state("gru.bias_hh_l0")
    hidden = np.zeros(weight_hh.shape[1], dtype=np.float32)
    for item in inputs:
        input_gates = weight_ih @ item + bias_ih
        hidden_gates = weight_hh @ hidden + bias_hh
        input_r, input_z, input_n = np.split(input_gates, 3)
        hidden_r, hidden_z, hidden_n = np.split(hidden_gates, 3)
        reset, update = sigmoid(input_r + hidden_r), sigmoid(input_z + hidden_z)
        candidate = np.tanh(input_n + reset * hidden_n)
        hidden = (1.0 - update) * candidate + update * hidden
    normalized = (hidden - hidden.mean()) / np.sqrt(hidden.var() + 1e-5)
    normalized = normalized * state("head.0.weight") + state("head.0.bias")
    hidden_head = gelu(state("head.1.weight") @ normalized + state("head.1.bias"))
    output = state("head.4.weight") @ hidden_head + state("head.4.bias")
    midpoint = len(output) // 2
    return output[:midpoint], output[midpoint:]


def eligible_members(database: Database) -> dict[str, list[int]]:
    rows = database.fetch_all(
        """
        SELECT i.id, i.sector
        FROM instruments i
        WHERE i.deleted_at IS NULL AND i.is_active=TRUE
          AND LOWER(i.type)='stock' AND NULLIF(i.sector, '') IS NOT NULL
          AND (SELECT COUNT(DISTINCT m.prediction_horizon_minutes)
               FROM trained_models m
               WHERE m.instrument_id=i.id AND m.deleted_at IS NULL
                 AND m.status='active'
                 AND m.feature_set_version='triple_daily_macro_v1'
                 AND m.prediction_horizon_minutes=ANY(%s)) = 4
        """,
        (list(STANDARD_HORIZON_MINUTES),),
    )
    output: dict[str, list[int]] = {}
    for row in rows:
        output.setdefault(str(row["sector"]), []).append(int(row["id"]))
    return output


def main() -> int:
    parser = argparse.ArgumentParser(description="Persist PyTorch sector 60T context")
    parser.add_argument("--artifact", type=Path, required=True)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--minimum-accuracy", type=float, default=0.52)
    parser.add_argument("--minimum-lift", type=float, default=0.02)
    parser.add_argument("--minimum-members", type=int, default=5)
    parser.add_argument("--years", type=int, default=10)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    model_values = np.load(args.artifact, allow_pickle=False)
    metadata = json.loads(str(model_values["metadata"]))
    report = json.loads(args.report.read_text(encoding="utf-8"))
    accuracy = weighted_metric(report, "direction_accuracy")
    lift = weighted_metric(report, "lift_vs_best_baseline")
    if accuracy < args.minimum_accuracy or lift < args.minimum_lift:
        raise RuntimeError(
            f"60T quality gate failed: accuracy={accuracy:.4f}, lift={lift:.4f}"
        )

    horizons = tuple(int(value) for value in metadata["horizons"])
    if HORIZON not in horizons:
        raise RuntimeError("Artifact does not contain the 60T head")
    sector_ids = {str(key): int(value) for key, value in metadata["sector_ids"].items()}
    sequence_length = int(metadata["sequence_length"])

    with Database() as database:
        eligible = eligible_members(database)
        repository = InstrumentRepository(database)
        market_data = MarketDataRepository(database)
        active = {item.symbol: item for item in repository.find_active()}
        observations_by_sector = {}
        for sector, info in report["sectors"].items():
            if sector not in sector_ids or len(eligible.get(sector, [])) < args.minimum_members:
                continue
            histories = {}
            for symbol in info.get("members", []):
                instrument = active.get(symbol)
                if instrument is None or instrument.sector != sector:
                    continue
                histories[symbol] = list(market_data.load_history(
                    int(instrument.id), "1d",
                    start=datetime.now(timezone.utc) - timedelta(days=366 * args.years),
                    end=datetime.now(timezone.utc), ascending=True,
                ))
            observations = aggregate_sector_history(
                histories, minimum_members=args.minimum_members
            )
            if len(observations) >= sequence_length:
                observations_by_sector[sector] = observations

        observations_by_sector = add_cross_sector_context(observations_by_sector)
        persisted = []
        for sector, observations in sorted(observations_by_sector.items()):
            sequence = np.asarray(
                [item.features for item in observations[-sequence_length:]],
                dtype=np.float32,
            )
            logits, returns = numpy_forward(sequence, sector_ids[sector], model_values)
            index = horizons.index(HORIZON)
            probability = float(sigmoid(logits[index]))
            expected_excess_return = float(returns[index])
            confidence = abs(probability - 0.5) * 200.0
            signal = "BUY" if probability >= 0.55 else "SELL" if probability <= 0.45 else "HOLD"
            prediction_date = observations[-1].timestamp.date()
            context_values = {
                "prediction_date": prediction_date,
                "scope_type": "sector60",
                "scope_key": sector,
                "score": probability * 10.0,
                "confidence": confidence,
                "signal": signal,
                "member_count": len(eligible[sector]),
                "meta": {
                    "source": "pytorch_sector_gru_60t",
                    "context_only": True,
                    "horizon_days": HORIZON,
                    "probability_up": probability,
                    "expected_sector_excess_return": expected_excess_return,
                    "artifact_version": str(metadata.get("trained_at", args.artifact.name)),
                    "quality_gate": {"accuracy": accuracy, "lift": lift},
                    "eligible_instrument_ids": eligible[sector],
                },
            }
            persisted.append(context_values)
            print(
                f"SECTOR60 {sector} date={prediction_date} p_up={probability:.4f} "
                f"excess={expected_excess_return:.4%} members={len(eligible[sector])}"
            )
            if args.dry_run:
                continue
            database.execute(
                """
                INSERT INTO market_context_predictions
                    (prediction_date, scope_type, scope_key, score, confidence,
                     signal, member_count, meta, created_at, updated_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s::jsonb,NOW(),NOW())
                ON CONFLICT (prediction_date, scope_type, scope_key)
                DO UPDATE SET score=EXCLUDED.score, confidence=EXCLUDED.confidence,
                    signal=EXCLUDED.signal, member_count=EXCLUDED.member_count,
                    meta=EXCLUDED.meta, updated_at=NOW()
                """,
                (
                    prediction_date, "sector60", sector, context_values["score"], confidence,
                    signal, context_values["member_count"], json.dumps(context_values["meta"]),
                ),
            )
        if not args.dry_run:
            database.commit()
    print(json.dumps({
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "quality_gate_passed": True,
        "accuracy": accuracy,
        "lift": lift,
        "contexts": len(persisted),
        "eligible_stocks": sum(item["member_count"] for item in persisted),
        "dry_run": args.dry_run,
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
