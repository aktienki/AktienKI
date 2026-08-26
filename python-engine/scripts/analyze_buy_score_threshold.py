from __future__ import annotations

import argparse
import json
import math

import numpy as np

from app.database.connection import Database
from app.repositories.instrument_repository import InstrumentRepository


def metrics(rows: list[dict], threshold: float) -> dict:
    selected = [row for row in rows if float(row["ki_score"]) >= threshold]
    returns = np.asarray([float(row["net_return"]) for row in selected], dtype=float)
    wins = returns[returns > 0]
    losses = returns[returns < 0]
    profit_factor = float(wins.sum() / abs(losses.sum())) if len(losses) and losses.sum() else None
    return {
        "threshold": round(threshold, 2),
        "trades": len(selected),
        "hit_rate": float((returns > 0).mean()) if len(returns) else None,
        "profit_factor": profit_factor,
        "average_net_return": float(returns.mean()) if len(returns) else None,
        "sum_net_return": float(returns.sum()) if len(returns) else None,
        "first_date": str(selected[0]["entry_date"]) if selected else None,
        "last_date": str(selected[-1]["entry_date"]) if selected else None,
    }


def range_metrics(rows: list[dict], minimum: float, maximum: float) -> dict:
    selected = [row for row in rows if minimum <= float(row["ki_score"]) <= maximum]
    returns = np.asarray([float(row["net_return"]) for row in selected], dtype=float)
    wins = returns[returns > 0]; losses = returns[returns < 0]
    return {
        "minimum": round(minimum, 2), "maximum": round(maximum, 2), "trades": len(selected),
        "hit_rate": float((returns > 0).mean()) if len(returns) else None,
        "profit_factor": float(wins.sum() / abs(losses.sum())) if len(losses) and losses.sum() else None,
        "average_net_return": float(returns.mean()) if len(returns) else None,
        "sum_net_return": float(returns.sum()) if len(returns) else None,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbol", default="ADS.DE")
    parser.add_argument("--run-id", type=int, default=471)
    parser.add_argument("--horizon", type=int, default=20, choices=[5, 10, 15, 20])
    parser.add_argument("--minimum-trades", type=int, default=8)
    parser.add_argument("--summary-only", action="store_true")
    args = parser.parse_args()

    with Database() as database:
        instrument = InstrumentRepository(database).find_by_symbol(args.symbol)
        rows = database.fetch_all(
            """
            WITH ordered AS (
                SELECT trade.entry_date, trade.ki_score, trade.net_return, trade.signal,
                       LAG(trade.signal) OVER (ORDER BY trade.entry_date, trade.id) AS previous_signal
                FROM backtest_trades trade
                WHERE trade.backtest_run_id = %s AND trade.instrument_id = %s
                  AND trade.horizon_days = %s AND trade.ki_score IS NOT NULL
                  AND trade.net_return IS NOT NULL
            )
            SELECT entry_date, ki_score, net_return
            FROM ordered
            WHERE signal = 'BUY' AND COALESCE(previous_signal, '') <> 'BUY'
            ORDER BY entry_date
            """,
            (args.run_id, instrument.id, args.horizon),
        )

    thresholds = sorted({round(value, 2) for value in np.arange(3.0, 10.01, .1)})
    results = [metrics(rows, threshold) for threshold in thresholds]
    qualified = [row for row in results if row["trades"] >= args.minimum_trades
                 and row["hit_rate"] is not None and row["hit_rate"] > .75
                 and row["profit_factor"] is not None and row["profit_factor"] > 1
                 and row["average_net_return"] is not None and row["average_net_return"] > 0]
    best = min(qualified, key=lambda row: row["threshold"]) if qualified else None
    nearest = sorted(
        [row for row in results if row["trades"] >= args.minimum_trades and row["hit_rate"] is not None],
        key=lambda row: (row["hit_rate"], row["profit_factor"] or -math.inf, row["trades"]), reverse=True,
    )[:10]
    threshold_curve = []
    previous_count = None
    for row in results:
        if row["trades"] != previous_count:
            threshold_curve.append(row)
            previous_count = row["trades"]
    range_candidates = []
    for minimum in thresholds:
        for maximum in thresholds:
            if maximum < minimum: continue
            row = range_metrics(rows, minimum, maximum)
            if (row["trades"] >= args.minimum_trades and row["hit_rate"] is not None and row["hit_rate"] > .75
                    and row["profit_factor"] is not None and row["profit_factor"] > 1
                    and row["average_net_return"] is not None and row["average_net_return"] > 0):
                range_candidates.append(row)
    range_candidates.sort(key=lambda row: (row["trades"], row["hit_rate"], row["profit_factor"]), reverse=True)
    payload = {
        "symbol": args.symbol, "run_id": args.run_id, "definition": "signal_change_to_buy_only",
        "horizon_days": args.horizon,
        "available_buy_transitions": len(rows), "minimum_trades": args.minimum_trades,
        "minimum_qualified_threshold": best, "top_candidates": nearest,
        "threshold_curve": threshold_curve,
        "qualified_score_ranges": range_candidates[:10],
        "buy_transitions": [] if args.summary_only else [
            {"date": str(row["entry_date"]), "ki_score": float(row["ki_score"]),
             "net_return": float(row["net_return"])} for row in rows
        ],
    }
    print(json.dumps(payload, indent=2, ensure_ascii=False, default=str))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
