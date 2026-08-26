from __future__ import annotations

import argparse
import json
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

from app.database.connection import Database


def calibrated_symbols() -> list[str]:
    with Database() as database:
        rows = database.fetch_all(
            """
            SELECT DISTINCT ON (UPPER(instrument.symbol)) instrument.symbol
            FROM stock_individual_thresholds threshold
            JOIN instruments instrument ON instrument.id = threshold.instrument_id
            WHERE threshold.horizon_days = 20
              AND instrument.deleted_at IS NULL
            ORDER BY UPPER(instrument.symbol), threshold.updated_at DESC, threshold.id DESC
            """
        )
    return sorted({str(row["symbol"]).upper() for row in rows})


def append_jsonl(path: Path, payload: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(payload, ensure_ascii=False, default=str) + "\n")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--shard-index", type=int, required=True)
    parser.add_argument("--shard-count", type=int, default=3)
    parser.add_argument("--output-dir", type=Path, default=Path("reports/beta123_quick_screen"))
    parser.add_argument("--limit", type=int)
    args = parser.parse_args()
    if not 0 <= args.shard_index < args.shard_count:
        parser.error("shard-index must be within shard-count")

    all_symbols = calibrated_symbols()
    symbols = [symbol for index, symbol in enumerate(all_symbols) if index % args.shard_count == args.shard_index]
    if args.limit is not None:
        symbols = symbols[: args.limit]
    args.output_dir.mkdir(parents=True, exist_ok=True)
    progress = args.output_dir / f"progress_shard_{args.shard_index}.jsonl"
    summary_path = args.output_dir / f"summary_shard_{args.shard_index}.json"
    completed = promoted = failed = 0
    test_year = datetime.now(timezone.utc).year - 1

    for position, symbol in enumerate(symbols, start=1):
        output = args.output_dir / f"{symbol.replace('/', '_')}.json"
        if output.exists():
            try:
                report = json.loads(output.read_text(encoding="utf-8"))
                decision = report.get("decision")
                completed += 1
                promoted += int(decision == "candidate_pending_context_filters")
                continue
            except (OSError, json.JSONDecodeError):
                pass
        started = datetime.now(timezone.utc)
        command = [
            sys.executable, "scripts/quick_screen_confidence_phase_20t.py",
            "--symbol", symbol, "--test-year", str(test_year), "--output", str(output),
        ]
        result = subprocess.run(command, text=True, capture_output=True, check=False)
        event = {
            "timestamp": datetime.now(timezone.utc).isoformat(), "shard": args.shard_index,
            "position": position, "shard_total": len(symbols), "universe_total": len(all_symbols),
            "symbol": symbol, "duration_seconds": (datetime.now(timezone.utc) - started).total_seconds(),
            "return_code": result.returncode,
        }
        if result.returncode == 0 and output.exists():
            report = json.loads(output.read_text(encoding="utf-8"))
            metrics = report.get("test", {}).get("metrics", {})
            event.update({
                "decision": report.get("decision"), "confidence": report.get("chosen_confidence"),
                "raw_trades": report.get("selectivity", {}).get("raw_executable_trades"),
                "trades": metrics.get("independent_trades"),
                "hit_rate": metrics.get("independent_win_rate"),
                "profit_factor": metrics.get("independent_profit_factor"),
                "raw_profit_factor": report.get("selectivity", {}).get("raw_profit_factor"),
                "retention_ratio": report.get("selectivity", {}).get("retention_ratio"),
                "average_return": metrics.get("independent_mean_return"),
            })
            promoted += int(event["decision"] == "candidate_pending_context_filters")
            completed += 1
        else:
            failed += 1
            event.update({"decision": "technical_failure", "stderr": result.stderr[-1500:]})
        append_jsonl(progress, event)
        summary = {
            "updated_at": datetime.now(timezone.utc).isoformat(), "shard": args.shard_index,
            "shard_total": len(symbols), "universe_total": len(all_symbols), "completed": completed,
            "promoted": promoted, "failed": failed, "remaining": len(symbols) - position,
            "last": event,
        }
        summary_path.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")
        print("BETA123_PROGRESS", json.dumps(event, ensure_ascii=False), flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
