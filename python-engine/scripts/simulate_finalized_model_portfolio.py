from __future__ import annotations

import argparse
import json
from collections import defaultdict
from datetime import date, datetime, timedelta
from pathlib import Path

from app.database import Database


def number(value: object) -> float:
    try:
        return float(value or 0.0)
    except (TypeError, ValueError):
        return 0.0


def classify(metrics: dict) -> str | None:
    trades = int(metrics.get("trades") or 0)
    pf = number(metrics.get("profit_factor"))
    hit = number(metrics.get("hit_rate"))
    performance = number(metrics.get("return_percent"))
    drawdown = number(metrics.get("maximum_closed_trade_drawdown_percent"))
    if trades >= 10 and pf >= 2.0 and hit >= 65 and performance > 0 and drawdown <= 25:
        return "quality"
    if trades >= 10 and pf >= 1.5 and hit >= 55 and performance > 0 and drawdown <= 30:
        return "solid"
    return None


def load_candidates(report_dirs: list[Path]) -> tuple[list[dict], list[dict]]:
    candidates: list[dict] = []
    excluded: list[dict] = []
    seen: set[str] = set()
    for report_dir in report_dirs:
        progress_path = report_dir / "progress.json"
        if not progress_path.is_file():
            continue
        progress = json.loads(progress_path.read_text(encoding="utf-8"))
        for summary in progress.get("results", []):
            symbol = str(summary.get("symbol") or "")
            if not symbol or symbol in seen:
                continue
            seen.add(symbol)
            detail_path = report_dir / f"{symbol}.json"
            if not detail_path.is_file():
                continue
            detail = json.loads(detail_path.read_text(encoding="utf-8"))
            ranked: list[tuple[float, str, dict]] = []
            for variant in ("A_baseline", "D_arima_timeseries"):
                result = detail.get("variants", {}).get(variant, {})
                account = result.get("account_simulation", {})
                ranked.append((number(account.get("return_percent")), variant, account))
            _, variant, account = max(ranked, key=lambda item: item[0])
            tier = classify(account)
            item = {
                "symbol": symbol,
                "variant": variant,
                "tier": tier,
                "metrics": account,
            }
            if tier is None:
                excluded.append(item)
            else:
                candidates.append(item)
    return candidates, excluded


def sp500_return(start: date, end: date) -> dict:
    database = Database()
    try:
        row = database.fetch_one(
            """
            SELECT id FROM instruments
            WHERE deleted_at IS NULL AND UPPER(symbol) IN ('SPY','^GSPC','GSPC','SPX','S&P 500')
            ORDER BY CASE UPPER(symbol) WHEN 'SPY' THEN 0 WHEN '^GSPC' THEN 1 ELSE 2 END
            LIMIT 1
            """
        )
        if row is None:
            raise LookupError("S&P-500-Instrument fehlt")
        first = database.fetch_one(
            """SELECT bar_time,close FROM price_bars
               WHERE instrument_id=%s AND interval IN ('1d','1day') AND bar_time::date >= %s
               ORDER BY bar_time ASC LIMIT 1""",
            (row["id"], start),
        )
        last = database.fetch_one(
            """SELECT bar_time,close FROM price_bars
               WHERE instrument_id=%s AND interval IN ('1d','1day') AND bar_time::date <= %s
               ORDER BY bar_time DESC LIMIT 1""",
            (row["id"], end),
        )
        if first is None or last is None:
            raise LookupError("S&P-500-Kursreihe unvollständig")
        first_close, last_close = number(first["close"]), number(last["close"])
        return {
            "start_date": str(first["bar_time"].date()),
            "end_date": str(last["bar_time"].date()),
            "start_price": round(first_close, 4),
            "end_price": round(last_close, 4),
            "return_percent": round((last_close / first_close - 1.0) * 100.0, 3),
        }
    finally:
        database.close()


def simulate(
    candidates: list[dict],
    *,
    start: date,
    end: date,
    initial_capital: float,
    max_positions: int,
) -> dict:
    entries: dict[date, list[dict]] = defaultdict(list)
    exits: dict[date, list[dict]] = defaultdict(list)
    for candidate in candidates:
        for trade in candidate["metrics"].get("trades_detail", []):
            entry_date = date.fromisoformat(trade["entry_date"])
            exit_date = date.fromisoformat(trade["exit_date"])
            if entry_date < start or exit_date > end:
                continue
            event = {
                "symbol": candidate["symbol"],
                "variant": candidate["variant"],
                "tier": candidate["tier"],
                "entry_date": entry_date,
                "exit_date": exit_date,
                "predicted_return_percent": number(trade.get("predicted_return_percent")),
                "net_return_percent": number(trade.get("net_return_percent")),
            }
            entries[entry_date].append(event)

    cash = float(initial_capital)
    positions: dict[str, dict] = {}
    completed: list[dict] = []
    rejected_for_capacity = 0
    peak = initial_capital
    max_drawdown = 0.0
    utilization_weighted_days = 0.0
    elapsed_days = 0
    previous_date = start

    calendar_dates = sorted(
        set(entries)
        | {
            event["exit_date"]
            for daily_entries in entries.values()
            for event in daily_entries
        }
    )
    for current_date in calendar_dates:
        if current_date < start or current_date > end:
            continue
        days = max(0, (current_date - previous_date).days)
        invested = sum(number(position["allocation"]) for position in positions.values())
        equity_cost = cash + invested
        if equity_cost > 0:
            utilization_weighted_days += days * invested / equity_cost
        elapsed_days += days
        previous_date = current_date

        for event in list(exits.get(current_date, [])):
            key = event["position_key"]
            position = positions.pop(key, None)
            if position is None:
                continue
            proceeds = position["allocation"] * (1.0 + position["net_return_percent"] / 100.0)
            cash += proceeds
            completed.append(
                {
                    **position,
                    "entry_date": str(position["entry_date"]),
                    "exit_date": str(position["exit_date"]),
                    "proceeds": round(proceeds, 2),
                }
            )

        available_slots = max_positions - len(positions)
        todays_entries = sorted(
            entries.get(current_date, []),
            key=lambda item: item["predicted_return_percent"],
            reverse=True,
        )
        for event in todays_entries:
            if available_slots <= 0:
                rejected_for_capacity += 1
                continue
            total_cost_equity = cash + sum(
                number(position["allocation"]) for position in positions.values()
            )
            target_allocation = total_cost_equity / max_positions
            allocation = min(cash, target_allocation)
            if allocation <= 0:
                rejected_for_capacity += 1
                continue
            key = f"{event['symbol']}:{event['entry_date']}:{len(completed)}"
            position = {**event, "position_key": key, "allocation": round(allocation, 2)}
            positions[key] = position
            exits[event["exit_date"]].append(position)
            cash -= allocation
            available_slots -= 1

        closed_equity = cash + sum(number(position["allocation"]) for position in positions.values())
        peak = max(peak, closed_equity)
        if peak > 0:
            max_drawdown = max(max_drawdown, (peak - closed_equity) / peak)

    for position in positions.values():
        cash += position["allocation"]
    final_capital = cash
    profits = [trade["proceeds"] - trade["allocation"] for trade in completed]
    gross_profit = sum(value for value in profits if value > 0)
    gross_loss = abs(sum(value for value in profits if value < 0))
    return {
        "start_date": str(start),
        "end_date": str(end),
        "initial_capital": round(initial_capital, 2),
        "final_capital": round(final_capital, 2),
        "profit": round(final_capital - initial_capital, 2),
        "return_percent": round((final_capital / initial_capital - 1.0) * 100.0, 3),
        "maximum_closed_trade_drawdown_percent": round(max_drawdown * 100.0, 3),
        "average_capital_utilization_percent": round(
            (utilization_weighted_days / max(1, elapsed_days)) * 100.0, 2
        ),
        "trades": len(completed),
        "hit_rate": round(
            sum(1 for value in profits if value > 0) / max(1, len(profits)) * 100.0, 2
        ),
        "profit_factor": round(gross_profit / gross_loss, 3) if gross_loss else None,
        "capacity_rejections": rejected_for_capacity,
        "max_positions": max_positions,
        "trades_detail": completed,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--report-dir", action="append", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--capital", type=float, default=10_000.0)
    parser.add_argument("--max-positions", type=int, default=5)
    parser.add_argument("--years", type=int, default=3)
    args = parser.parse_args()

    end = datetime.now().date()
    start = end - timedelta(days=round(args.years * 365.25))
    candidates, excluded = load_candidates(args.report_dir)
    portfolio = simulate(
        candidates,
        start=start,
        end=end,
        initial_capital=args.capital,
        max_positions=args.max_positions,
    )
    benchmark = sp500_return(start, end)
    benchmark_final = args.capital * (1.0 + benchmark["return_percent"] / 100.0)
    result = {
        "method": "finalized_model_league_20t_buy_crossings",
        "candidate_count": len(candidates),
        "excluded_count": len(excluded),
        "tiers": {
            "quality": sum(item["tier"] == "quality" for item in candidates),
            "solid": sum(item["tier"] == "solid" for item in candidates),
        },
        "portfolio": portfolio,
        "sp500_buy_and_hold": {
            **benchmark,
            "initial_capital": round(args.capital, 2),
            "final_capital": round(benchmark_final, 2),
            "profit": round(benchmark_final - args.capital, 2),
        },
        "excess_return_percentage_points": round(
            portfolio["return_percent"] - benchmark["return_percent"], 3
        ),
        "candidates": [
            {"symbol": item["symbol"], "variant": item["variant"], "tier": item["tier"]}
            for item in candidates
        ],
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps({key: value for key, value in result.items() if key != "candidates"}, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
