from __future__ import annotations

import argparse
import json
from datetime import datetime, timedelta, timezone
from math import erf, sqrt
from pathlib import Path
from statistics import fmean, pstdev

import numpy as np

from app.config.settings import settings
from app.database.connection import Database
from app.repositories.instrument_repository import InstrumentRepository
from app.repositories.market_data_repository import MarketDataRepository


def daily_closes(bars) -> dict[datetime, float]:
    output = {}
    for bar in bars:
        stamp = bar.timestamp
        if stamp.tzinfo is not None:
            stamp = stamp.replace(tzinfo=None)
        stamp = stamp.replace(hour=0, minute=0, second=0, microsecond=0)
        if float(bar.close) > 0:
            output[stamp] = float(bar.close)
    return output


def observations(stock_bars, market_bars) -> list[tuple[datetime, tuple[float, ...]]]:
    stock, market = daily_closes(stock_bars), daily_closes(market_bars)
    dates = sorted(set(stock).intersection(market))
    stock_prices, market_prices = [stock[d] for d in dates], [market[d] for d in dates]
    output = []
    for index in range(200, len(dates)):
        def ret(values, days):
            return values[index] / values[index - days] - 1.0
        stock_returns = [stock_prices[i] / stock_prices[i - 1] - 1 for i in range(index - 19, index + 1)]
        market_returns = [market_prices[i] / market_prices[i - 1] - 1 for i in range(index - 19, index + 1)]
        stock_1, stock_5, stock_20 = ret(stock_prices, 1), ret(stock_prices, 5), ret(stock_prices, 20)
        market_1, market_5, market_20 = ret(market_prices, 1), ret(market_prices, 5), ret(market_prices, 20)
        output.append((dates[index], (
            stock_1, stock_5, stock_20, ret(stock_prices, 60), pstdev(stock_returns) * sqrt(252),
            sum(value > 0 for value in stock_returns) / len(stock_returns),
            float(stock_prices[index] > fmean(stock_prices[index - 19:index + 1])),
            float(stock_prices[index] > fmean(stock_prices[index - 49:index + 1])),
            float(stock_prices[index] > fmean(stock_prices[index - 199:index + 1])),
            pstdev(stock_returns), 1.0, market_1, market_5, market_20,
            pstdev(market_returns) * sqrt(252), stock_1 - market_1,
            stock_5 - market_5, stock_20 - market_20,
        )))
    return output


def gelu(values: np.ndarray) -> np.ndarray:
    return 0.5 * values * (1.0 + np.vectorize(erf)(values / sqrt(2.0)))


def sigmoid(values: np.ndarray) -> np.ndarray:
    return 1.0 / (1.0 + np.exp(-np.clip(values, -40.0, 40.0)))


def conv1d_same(sequence: np.ndarray, weight: np.ndarray, bias: np.ndarray) -> np.ndarray:
    # PyTorch Conv1d uses cross-correlation and symmetric zero padding here.
    channels, length = sequence.shape
    output_channels, _, kernel = weight.shape
    padded = np.pad(sequence, ((0, 0), (kernel // 2, kernel // 2)))
    output = np.empty((output_channels, length), dtype=np.float32)
    for index in range(length):
        output[:, index] = np.einsum('ock,ck->o', weight, padded[:, index:index + kernel]) + bias
    return output


def numpy_forward(sequence: np.ndarray, values, phase: str) -> np.ndarray:
    def state(name: str) -> np.ndarray:
        return np.asarray(values[f"{phase}__{name}"], dtype=np.float32)
    mean, std = state("mean"), state("std")
    std = np.where(std < 1e-8, 1.0, std)
    temporal = ((sequence - mean) / std).T
    temporal = gelu(conv1d_same(temporal, state("temporal.0.weight"), state("temporal.0.bias")))
    temporal = gelu(conv1d_same(temporal, state("temporal.2.weight"), state("temporal.2.bias"))).T
    embedding = state("sector_embedding.weight")[0]
    inputs = np.concatenate((temporal, np.repeat(embedding[None, :], len(temporal), axis=0)), axis=1)
    weight_ih, weight_hh = state("gru.weight_ih_l0"), state("gru.weight_hh_l0")
    bias_ih, bias_hh = state("gru.bias_ih_l0"), state("gru.bias_hh_l0")
    hidden_size = weight_hh.shape[1]
    hidden = np.zeros(hidden_size, dtype=np.float32)
    for item in inputs:
        input_gates, hidden_gates = weight_ih @ item + bias_ih, weight_hh @ hidden + bias_hh
        input_r, input_z, input_n = np.split(input_gates, 3)
        hidden_r, hidden_z, hidden_n = np.split(hidden_gates, 3)
        reset, update = sigmoid(input_r + hidden_r), sigmoid(input_z + hidden_z)
        candidate = np.tanh(input_n + reset * hidden_n)
        hidden = (1.0 - update) * candidate + update * hidden
    normalized = (hidden - hidden.mean()) / np.sqrt(hidden.var() + 1e-5)
    normalized = normalized * state("head.0.weight") + state("head.0.bias")
    hidden_head = gelu(state("head.1.weight") @ normalized + state("head.1.bias"))
    return state("head.4.weight") @ hidden_head + state("head.4.bias")


def database_bars(
    symbol: str,
    instruments: InstrumentRepository,
    market_data: MarketDataRepository,
    cache: dict[str, list],
) -> list:
    """Load an already refreshed daily series once per batch.

    Daily filter inference must never trigger another provider import. The core
    prediction batch owns market-data refresh; filters consume that snapshot.
    Shared benchmarks are cached across all stock artifacts.
    """
    if symbol in cache:
        return cache[symbol]
    instrument = instruments.find_by_symbol(symbol)
    if instrument is None or instrument.id is None:
        raise RuntimeError(f"{symbol}: instrument not found")
    cache[symbol] = list(market_data.load_history(
        int(instrument.id), "1d",
        start=datetime.now(timezone.utc) - timedelta(days=3660),
        end=datetime.now(timezone.utc), ascending=True,
    ))
    return cache[symbol]


def predict(
    artifact: Path,
    database: Database,
    instruments: InstrumentRepository,
    market_data: MarketDataRepository,
    bars_cache: dict[str, list],
) -> dict:
    values = np.load(artifact, allow_pickle=False)
    metadata = json.loads(str(values["metadata"]))
    symbol, benchmark = str(metadata["symbol"]), str(metadata["benchmark"])
    history = observations(
        # Stock series are released after each inference; only the handful of
        # shared benchmark series remain cached for the 5,000-stock batch.
        database_bars(symbol, instruments, market_data, {}),
        database_bars(benchmark, instruments, market_data, bars_cache),
    )
    length = int(metadata["sequence_length"])
    if len(history) < length:
        raise RuntimeError(f"{symbol}: only {len(history)} observations")
    sequence = np.asarray([features for _, features in history[-length:]], dtype=np.float32)
    market_return, market_volatility = float(sequence[-1, 13]), float(sequence[-1, 14])
    phase = "stress" if market_return < -0.02 else "bull" if market_return > 0.02 else "sideways"
    logits = numpy_forward(sequence, values, phase)
    probability = float(sigmoid(logits)[list(metadata["horizons"]).index(20)])
    quality = metadata.get("quality_gate", {})
    meta = {
        "source": "pytorch_stock_three_phase_gru_20t", "context_only": True,
        "filter_only": True, "enabled": bool(quality.get("enabled", False)),
        "phase": phase, "probability_up": probability, "threshold": 0.5,
        "benchmark": benchmark, "artifact": artifact.name, "quality_gate": quality,
    }
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
        (history[-1][0].date(), str(metadata["instrument_id"]), probability * 10.0,
         abs(probability - 0.5) * 200.0, "BUY" if probability >= 0.5 else "SELL", json.dumps(meta)),
    )
    return {"symbol": symbol, "phase": phase, "probability_20d": probability,
            "enabled": meta["enabled"], "date": history[-1][0].date().isoformat()}


def main() -> int:
    parser = argparse.ArgumentParser(description="Refresh stock three-phase PyTorch filters")
    parser.add_argument("--artifact-dir", type=Path, default=settings.model_path / "phase_filters")
    parser.add_argument("--symbols", nargs="*")
    args = parser.parse_args()
    selected = {value.upper() for value in (args.symbols or [])}
    completed, failed = [], []
    with Database() as database:
        instruments = InstrumentRepository(database)
        market_data = MarketDataRepository(database)
        bars_cache: dict[str, list] = {}
        for artifact in sorted(args.artifact_dir.glob("*_three_phase_20t.npz")):
            try:
                with np.load(artifact, allow_pickle=False) as content:
                    metadata = json.loads(str(content["metadata"]))
                if selected and str(metadata["symbol"]).upper() not in selected:
                    continue
                row = predict(
                    artifact, database, instruments, market_data, bars_cache,
                )
                completed.append(row)
                print("PHASE_FILTER " + json.dumps(row), flush=True)
            except Exception as exception:
                failed.append({"artifact": artifact.name, "error": str(exception)})
                print(f"PHASE_FILTER_FAILED artifact={artifact.name} error={exception}", flush=True)
        database.commit()
    print(json.dumps({"completed": len(completed), "failed": len(failed), "errors": failed}))
    return 0 if completed or not failed else 1


if __name__ == "__main__":
    raise SystemExit(main())
