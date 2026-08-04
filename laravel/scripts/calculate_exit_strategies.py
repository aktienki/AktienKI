from __future__ import annotations

import argparse
import json
import os
import sys
from bisect import bisect_right
from datetime import date, datetime, time, timedelta, timezone
from pathlib import Path
from types import SimpleNamespace
from typing import Callable


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


COUNTRY_BENCHMARKS = {
    "US": "^GSPC", "DE": "^GDAXI", "GB": "^FTSE", "FR": "^FCHI",
    "NL": "^AEX", "CH": "^SSMI", "JP": "^N225", "CN": "000001.SS",
    "HK": "^HSI", "AU": "^AXJO", "CA": "^GSPTSE",
}


def _momentum(closes: list[float], position: int, days: int = 20) -> float | None:
    if position < days or closes[position - days] <= 0:
        return None
    return closes[position] / closes[position - days] - 1.0


def run(run_id: int, progress_callback: Callable[[int, int], None] | None = None) -> dict:
    with Database() as database:
        run_record = database.fetch_one("SELECT settings FROM backtest_runs WHERE id=%s", (run_id,)) or {}
        rows = database.fetch_all(
            """SELECT trade.id, trade.instrument_id, trade.entry_date, trade.exit_date,
                      trade.entry_price, trade.exit_price, trade.gross_return,
                      trade.max_drawdown, trade.predicted_return, trade.ki_score, instrument.symbol,
                      instrument.country, instrument.sector
               FROM backtest_trades trade
               JOIN instruments instrument ON instrument.id=trade.instrument_id
               WHERE trade.backtest_run_id=%s
               ORDER BY trade.entry_date, trade.ki_score DESC, trade.confidence DESC, trade.id""",
            (run_id,),
        )
        memberships = database.fetch_all(
            """SELECT DISTINCT ON (membership.instrument_id)
                      membership.instrument_id, market_index.symbol
               FROM index_memberships membership
               JOIN market_indices market_index ON market_index.id=membership.market_index_id
               WHERE membership.instrument_id = ANY(%s)
               ORDER BY membership.instrument_id, membership.weight DESC NULLS LAST, market_index.id""",
            ([int(row["instrument_id"]) for row in rows],),
        ) if rows else []
    settings = run_record.get("settings") or {}
    if isinstance(settings, str):
        settings = json.loads(settings)
    selection_filters = settings.get("selection_filters") or {}
    sector_score_rotation = str(selection_filters.get("sector_score_rotation", "0")).lower() in {"1", "true", "yes", "on"}
    index_score_rotation = str(selection_filters.get("index_score_rotation", "0")).lower() in {"1", "true", "yes", "on"}
    index_by_instrument = {int(item["instrument_id"]): str(item["symbol"]) for item in memberships}
    for row in rows:
        row["market_index"] = index_by_instrument.get(int(row["instrument_id"]), "")
    model = _exit_model()["model"]
    results = []
    by_symbol: dict[str, list[dict]] = {}
    for row in rows:
        by_symbol.setdefault(row["symbol"], []).append(row)

    instrument_ids = sorted({int(row["instrument_id"]) for row in rows})
    bars_by_instrument: dict[int, list[SimpleNamespace]] = {}
    benchmark_bars: dict[str, list[SimpleNamespace]] = {}
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
            benchmark_rows = database.fetch_all(
                """SELECT instrument.symbol, instrument.provider_symbol,
                          bar.bar_time, bar.open, bar.high, bar.low, bar.close
                   FROM price_bars bar
                   JOIN instruments instrument ON instrument.id=bar.instrument_id
                   WHERE instrument.type='index' AND bar.interval='1d'
                     AND (instrument.symbol = ANY(%s) OR instrument.provider_symbol = ANY(%s))
                     AND bar.bar_time BETWEEN %s AND %s
                   ORDER BY instrument.id, bar.bar_time""",
                (list(set(COUNTRY_BENCHMARKS.values())), list(set(COUNTRY_BENCHMARKS.values())), history_start, history_end),
            )
        for bar in price_rows:
            bars_by_instrument.setdefault(int(bar["instrument_id"]), []).append(SimpleNamespace(
                timestamp=bar["bar_time"],
                open=bar["open"], high=bar["high"], low=bar["low"], close=bar["close"],
            ))
        for bar in benchmark_rows:
            key = bar["provider_symbol"] or bar["symbol"]
            benchmark_bars.setdefault(key, []).append(SimpleNamespace(
                timestamp=bar["bar_time"], open=bar["open"], high=bar["high"],
                low=bar["low"], close=bar["close"],
            ))

    benchmark_dates = {
        symbol: [bar.timestamp.date() for bar in bars]
        for symbol, bars in benchmark_bars.items()
    }
    benchmark_closes = {
        symbol: [float(bar.close) for bar in bars]
        for symbol, bars in benchmark_bars.items()
    }
    sector_rank_cache: dict[date, dict[str, float]] = {}
    daily_best: dict[date, dict[str, object]] = {}
    for trade in rows:
        entry_day = trade["entry_date"]
        if isinstance(entry_day, str):
            entry_day = date.fromisoformat(entry_day)
        bucket = daily_best.setdefault(entry_day, {"sectors": {}, "indices": {}})
        score = float(trade.get("ki_score") or 0.0)
        sector = str(trade.get("sector") or "")
        market_index = str(trade.get("market_index") or "")
        if sector:
            bucket["sectors"].setdefault(sector, []).append(score)
        if market_index:
            bucket["indices"].setdefault(market_index, []).append(score)
    for bucket in daily_best.values():
        sector_scores = {key: sum(values) / len(values) for key, values in bucket["sectors"].items()}
        index_scores = {key: sum(values) / len(values) for key, values in bucket["indices"].items()}
        bucket["sector_scores"] = sector_scores
        bucket["index_scores"] = index_scores
        bucket["best_sector"] = max(sector_scores, key=sector_scores.get) if sector_scores else None
        bucket["best_index"] = max(index_scores, key=index_scores.get) if index_scores else None

    def sector_momenta(entry_day: date) -> dict[str, float]:
        if entry_day in sector_rank_cache:
            return sector_rank_cache[entry_day]
        start_day = entry_day - timedelta(days=90)
        grouped: dict[str, list[float]] = {}
        for historical_trade in rows:
            exit_day = historical_trade["exit_date"]
            if isinstance(exit_day, str):
                exit_day = date.fromisoformat(exit_day)
            sector = str(historical_trade.get("sector") or "")
            if sector and start_day <= exit_day < entry_day:
                grouped.setdefault(sector, []).append(float(historical_trade["gross_return"] or 0.0))
        values = {
            sector: sum(returns) / len(returns)
            for sector, returns in grouped.items()
            if len(returns) >= 2
        }
        sector_rank_cache[entry_day] = values
        return values

    def adaptive_decision(trade: dict, entry_day: date) -> tuple[bool, dict]:
        benchmark_symbol = COUNTRY_BENCHMARKS.get(str(trade.get("country") or "").upper(), "^GSPC")
        resolved_benchmark = benchmark_symbol if benchmark_symbol in benchmark_bars else "^GSPC"
        bars = benchmark_bars.get(resolved_benchmark) or []
        dates = benchmark_dates.get(resolved_benchmark, [])
        position = bisect_right(dates, entry_day) - 1
        if position < 49:
            return True, {"regime": "insufficient_data", "benchmark": benchmark_symbol}
        closes = benchmark_closes.get(resolved_benchmark, [])
        market_momentum = _momentum(closes, position, 20)
        sma50 = sum(closes[position - 49:position + 1]) / 50
        market_weak = closes[position] < sma50 or (market_momentum is not None and market_momentum < 0)
        if not market_weak:
            return True, {"regime": "normal", "benchmark": benchmark_symbol, "market_momentum": market_momentum}
        momenta = sector_momenta(entry_day)
        eligible = [
            sector for sector, momentum in sorted(momenta.items(), key=lambda item: item[1], reverse=True)
            if momentum > 0 and (market_momentum is None or momentum > market_momentum)
        ][:3]
        sector = str(trade.get("sector") or "")
        return sector in eligible, {
            "regime": "weak", "benchmark": benchmark_symbol,
            "market_momentum": market_momentum, "sector": sector,
            "sector_momentum": momenta.get(sector), "eligible_sectors": eligible,
        }

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
                adaptive_allowed, adaptive_metadata = adaptive_decision(trade, entry_day)
                if adaptive_allowed:
                    score_context = daily_best.get(entry_day, {})
                    best_sector = score_context.get("best_sector")
                    best_index = score_context.get("best_index")
                    sector_overweight = sector_score_rotation and trade.get("sector") == best_sector
                    index_overweight = index_score_rotation and trade.get("market_index") == best_index
                    allocation_weight = 1.5 if sector_overweight or index_overweight else 1.0
                    results.append((
                        run_id, trade["id"], trade["instrument_id"], "adaptive_rotation_20d",
                        trade["entry_date"], trade["exit_date"], trade["entry_price"],
                        trade["exit_price"], trade["gross_return"], trade["max_drawdown"],
                        json.dumps({
                            "holding_days": 20, "engine": "adaptive_rotation_v2",
                            "sector_source": "completed_prior_trades_90d",
                            "allocation_weight": allocation_weight,
                            "sector_score_rotation": sector_score_rotation,
                            "index_score_rotation": index_score_rotation,
                            "best_score_sector": best_sector,
                            "best_score_sector_average": (score_context.get("sector_scores") or {}).get(best_sector),
                            "best_score_index": best_index,
                            "best_score_index_average": (score_context.get("index_scores") or {}).get(best_index),
                            "sector_overweight": sector_overweight,
                            "index_overweight": index_overweight,
                            **adaptive_metadata,
                        }),
                    ))
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
            if progress_callback is not None:
                progress_callback(position, len(by_symbol))

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
    return {"run_id": run_id, "strategies": 4, "rows": len(results), "symbols": len(by_symbol)}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--run-id", type=int, required=True)
    args = parser.parse_args()
    print(json.dumps(run(args.run_id), ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
