from __future__ import annotations

import argparse
import json
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import UTC, datetime
from pathlib import Path

from app.database import Database


def active_model_symbols() -> list[str]:
    database = Database()
    try:
        rows = database.fetch_all(
            """
            SELECT DISTINCT i.symbol
            FROM instruments i
            JOIN trained_models tm ON tm.instrument_id=i.id
            WHERE i.deleted_at IS NULL
              AND tm.deleted_at IS NULL
              AND lower(i.type)='stock'
              AND tm.status='active'
            ORDER BY i.symbol
            """
        )
        return [str(row["symbol"]) for row in rows]
    finally:
        database.close()


def account_summary(payload: dict, variant: str) -> dict:
    account = payload.get("variants", {}).get(variant, {}).get("account_simulation", {})
    return {
        key: account.get(key)
        for key in (
            "trades",
            "hit_rate",
            "profit_factor",
            "average_return_percent",
            "final_capital",
            "return_percent",
            "maximum_closed_trade_drawdown_percent",
            "capital_utilization_percent",
        )
    }


def result_summary(path: Path) -> dict:
    payload = json.loads(path.read_text(encoding="utf-8"))
    baseline = account_summary(payload, "A_baseline")
    candidate = account_summary(payload, "D_arima_timeseries")
    return {
        "symbol": payload.get("symbol"),
        "oos_years": payload.get("oos_years"),
        "variants": {
            "A_baseline": baseline,
            "D_arima_timeseries": candidate,
        },
        "candidate_return_uplift_pp": round(
            float(candidate.get("return_percent") or 0)
            - float(baseline.get("return_percent") or 0),
            3,
        ),
        "candidate_pf_uplift": round(
            float(candidate.get("profit_factor") or 0)
            - float(baseline.get("profit_factor") or 0),
            3,
        ),
    }


def write_progress(
    path: Path,
    *,
    device: str,
    selected: list[str],
    rows: list[dict],
    failures: list[dict],
) -> None:
    path.write_text(
        json.dumps(
            {
                "device": device,
                "updated_at": datetime.now(UTC).isoformat(),
                "selected": len(selected),
                "completed": len(rows),
                "failed": len(failures),
                "remaining": len(selected) - len(rows) - len(failures),
                "results": rows,
                "failures": failures,
            },
            indent=2,
            ensure_ascii=False,
        )
        + "\n",
        encoding="utf-8",
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--device", required=True)
    parser.add_argument("--years", type=int, default=10)
    parser.add_argument("--shard-index", type=int, required=True)
    parser.add_argument("--shard-count", type=int, default=2)
    parser.add_argument("--workers", type=int, default=1)
    args = parser.parse_args()

    if not 0 <= args.shard_index < args.shard_count:
        raise ValueError("shard-index muss zwischen 0 und shard-count-1 liegen")

    symbols = active_model_symbols()
    selected = [
        symbol for index, symbol in enumerate(symbols)
        if index % args.shard_count == args.shard_index
    ]
    args.output_dir.mkdir(parents=True, exist_ok=True)
    progress_path = args.output_dir / "progress.json"

    rows: list[dict] = []
    failures: list[dict] = []

    pending: list[str] = []
    for symbol in selected:
        output = args.output_dir / f"{symbol}.json"
        if output.is_file():
            try:
                rows.append(result_summary(output))
                write_progress(
                    progress_path,
                    device=args.device,
                    selected=selected,
                    rows=rows,
                    failures=failures,
                )
                continue
            except (OSError, ValueError, KeyError, json.JSONDecodeError):
                output.unlink(missing_ok=True)

        pending.append(symbol)

    def run_symbol(symbol: str) -> tuple[str, dict | None, dict | None]:
        output = args.output_dir / f"{symbol}.json"

        command = [
            sys.executable,
            str(Path(__file__).with_name("test_timeseries_pytorch_meta_20t.py")),
            "--symbol",
            symbol,
            "--years",
            str(args.years),
            "--variants",
            "A_baseline,D_arima_timeseries",
            "--output",
            str(output),
        ]
        try:
            subprocess.run(
                command,
                check=True,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.PIPE,
                text=True,
            )
            return symbol, result_summary(output), None
        except subprocess.CalledProcessError as error:
            return symbol, None, {
                "symbol": symbol,
                "returncode": error.returncode,
                "error": error.stderr[-2000:],
            }

    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as executor:
        futures = [executor.submit(run_symbol, symbol) for symbol in pending]
        for future in as_completed(futures):
            _, result, failure = future.result()
            if result is not None:
                rows.append(result)
            if failure is not None:
                failures.append(failure)
            write_progress(
                progress_path,
                device=args.device,
                selected=selected,
                rows=rows,
                failures=failures,
            )


if __name__ == "__main__":
    main()
