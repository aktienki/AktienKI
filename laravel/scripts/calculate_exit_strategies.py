from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import date, datetime, time, timedelta, timezone
from pathlib import Path
from types import SimpleNamespace


def _engine_root() -> Path:
    configured = os.environ.get("AKTIENKI_PYTHON_ENGINE_PATH")
    if configured:
        return Path(configured).expanduser().resolve()
    return Path.home() / "Downloads" / "python-engine"


ENGINE_ROOT = _engine_root()
sys.path.insert(0, str(ENGINE_ROOT))
sys.path.insert(0, str(ENGINE_ROOT / "scripts"))

from app.database.connection import Database  # noqa: E402
from scripts.backtest_ai_exit_vs_sp500 import _exit_model, _winner_runner_exit  # noqa: E402


def _drawdown(bars, entry: int, exit_index: int, entry_price: float) -> float:
    lows = [float(bar.low) for bar in bars[entry : exit_index + 1]]
    return max(0.0, 1.0 - min(lows) / entry_price) if lows else 0.0


def run(run_id: int) -> dict:
    with Database() as database:
        rows = database.fetch_all(
            """SELECT trade.id, trade.instrument_id, trade.entry_date,
                      trade.predicted_return, instrument.symbol
               FROM backtest_trades trade
               JOIN instruments instrument ON instrument.id=trade.instrument_id
               WHERE trade.backtest_run_id=%s
               ORDER BY trade.entry_date, trade.ki_score DESC, trade.confidence DESC, trade.id""",
            (run_id,),
        )
    model = _exit_model()["model"]
    results = []
    by_symbol: dict[str, list[dict]] = {}
    for row in rows:
        by_symbol.setdefault(row["symbol"], []).append(row)

    instrument_ids = sorted({int(row["instrument_id"]) for row in rows})
    bars_by_instrument: dict[int, list[SimpleNamespace]] = {}
    if rows:
        first_entry = min(row["entry_date"] for row in rows)
        last_entry = max(row["entry_date"] for row in rows)
        if isinstance(first_entry, str):
            first_entry = date.fromisoformat(first_entry)
        if isinstance(last_entry, str):
            last_entry = date.fromisoformat(last_entry)
        history_start = datetime.combine(first_entry - timedelta(days=400), time.min, timezone.utc)
        history_end = datetime.combine(last_entry + timedelta(days=150), time.max, timezone.utc)
        with Database() as database:
            price_rows = database.fetch_all(
                """SELECT instrument_id, bar_time, open, high, low, close
                   FROM price_bars
                   WHERE instrument_id = ANY(%s) AND interval='1d'
                     AND bar_time BETWEEN %s AND %s
                   ORDER BY instrument_id, bar_time""",
                (instrument_ids, history_start, history_end),
            )
        for bar in price_rows:
            bars_by_instrument.setdefault(int(bar["instrument_id"]), []).append(SimpleNamespace(
                timestamp=bar["bar_time"],
                open=bar["open"], high=bar["high"], low=bar["low"], close=bar["close"],
            ))

    with Database() as database:
        database.execute(
            "UPDATE backtest_runs SET instruments_total=%s, instruments_completed=0, updated_at=NOW() WHERE id=%s",
            (len(by_symbol), run_id),
        )
        for position, (symbol, trades) in enumerate(by_symbol.items(), start=1):
            bars = bars_by_instrument.get(int(trades[0]["instrument_id"]), [])
            closes = [float(bar.close) for bar in bars]
            positions = {bar.timestamp.date(): index for index, bar in enumerate(bars)}
            for trade in trades:
                entry_day = trade["entry_date"]
                if isinstance(entry_day, str):
                    entry_day = date.fromisoformat(entry_day)
                entry = positions.get(entry_day)
                if entry is None or entry + 20 >= len(bars):
                    continue
                entry_price = float(bars[entry].open)
                fixed_exit = min(entry + 20, len(bars) - 1)
                runner_exit, runner_price = _winner_runner_exit(bars, closes, entry, model)
                predicted_return = max(0.0, float(trade["predicted_return"] or 0.0))
                target_price = entry_price * (1.0 + predicted_return)
                target_exit = fixed_exit
                target_exit_price = closes[fixed_exit]
                if predicted_return > 0.0:
                    for target_index in range(entry + 1, fixed_exit + 1):
                        if float(bars[target_index].high) >= target_price:
                            target_exit = target_index
                            target_exit_price = target_price
                            break
                for strategy, exit_index, exit_price in (
                    ("fixed_20d", fixed_exit, closes[fixed_exit]),
                    ("winner_runner", runner_exit, runner_price),
                    ("prediction_target", target_exit, target_exit_price),
                ):
                    results.append((
                        run_id,
                        trade["id"],
                        trade["instrument_id"],
                        strategy,
                        bars[entry].timestamp.date(),
                        bars[exit_index].timestamp.date(),
                        entry_price,
                        exit_price,
                        exit_price / entry_price - 1.0,
                        _drawdown(bars, entry, exit_index, entry_price),
                        json.dumps({"holding_days": exit_index - entry, "engine": "winner_runner_v1"}),
                    ))
            print(f"EXIT_STRATEGY_PROGRESS {position}/{len(by_symbol)} {symbol}", flush=True)

            database.execute(
                "UPDATE backtest_runs SET instruments_completed=%s, updated_at=NOW() WHERE id=%s AND status <> 'cancelled'",
                (position, run_id),
            )

        if results:
            with database.cursor() as cursor:
                cursor.executemany(
                """INSERT INTO backtest_strategy_trades (
                       backtest_run_id, backtest_trade_id, instrument_id, strategy,
                       entry_date, exit_date, entry_price, exit_price, gross_return,
                       max_drawdown, metadata, created_at, updated_at
                   ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s::jsonb,NOW(),NOW())
                   ON CONFLICT (backtest_run_id,backtest_trade_id,strategy) DO UPDATE SET
                       exit_date=EXCLUDED.exit_date, entry_price=EXCLUDED.entry_price,
                       exit_price=EXCLUDED.exit_price, gross_return=EXCLUDED.gross_return,
                       max_drawdown=EXCLUDED.max_drawdown, metadata=EXCLUDED.metadata,
                       updated_at=NOW()""",
                    results,
                )
    return {"run_id": run_id, "strategies": 3, "rows": len(results), "symbols": len(by_symbol)}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--run-id", type=int, required=True)
    args = parser.parse_args()
    print(json.dumps(run(args.run_id), ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
