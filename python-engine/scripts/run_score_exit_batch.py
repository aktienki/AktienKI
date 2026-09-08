from __future__ import annotations

import argparse
import json
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import UTC, datetime
from pathlib import Path


def summary(path: Path) -> dict:
    payload = json.loads(path.read_text(encoding="utf-8"))
    variants = {}
    for name, result in payload.get("variants", {}).items():
        sweep = result.get("score_exit_sweep", {})
        variants[name] = {
            "status": sweep.get("status"),
            "selected": sweep.get("selected"),
            "calibration": sweep.get("calibration"),
            "validation": {
                key: value
                for key, value in sweep.get("validation", {}).items()
                if key != "trades_detail"
            },
        }
    return {"symbol": payload.get("symbol"), "variants": variants}


def write_progress(path: Path, selected: list[str], rows: list[dict], failures: list[dict]) -> None:
    path.write_text(
        json.dumps(
            {
                "updated_at": datetime.now(UTC).isoformat(),
                "selected": len(selected),
                "completed": len(rows),
                "failed": len(failures),
                "remaining": len(selected) - len(rows) - len(failures),
                "results": sorted(rows, key=lambda row: row["symbol"]),
                "failures": sorted(failures, key=lambda row: row["symbol"]),
            },
            indent=2,
            ensure_ascii=False,
        ) + "\n",
        encoding="utf-8",
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbols", required=True)
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--years", type=int, default=30)
    parser.add_argument("--workers", type=int, default=3)
    args = parser.parse_args()
    selected = [symbol.strip() for symbol in args.symbols.split(",") if symbol.strip()]
    args.output_dir.mkdir(parents=True, exist_ok=True)
    progress = args.output_dir / "progress.json"
    rows: list[dict] = []
    failures: list[dict] = []

    def run(symbol: str) -> tuple[dict | None, dict | None]:
        output = args.output_dir / f"{symbol.replace('.', '_')}.json"
        command = [
            sys.executable,
            str(Path(__file__).with_name("test_timeseries_pytorch_meta_20t.py")),
            "--symbol", symbol,
            "--years", str(args.years),
            "--variants", "D_arima_timeseries,E_arima_timeseries_pytorch",
            "--output", str(output),
        ]
        try:
            subprocess.run(command, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
            return summary(output), None
        except subprocess.CalledProcessError as error:
            return None, {"symbol": symbol, "returncode": error.returncode, "error": error.stderr[-2000:]}

    write_progress(progress, selected, rows, failures)
    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as executor:
        futures = {executor.submit(run, symbol): symbol for symbol in selected}
        for future in as_completed(futures):
            result, failure = future.result()
            if result:
                rows.append(result)
            if failure:
                failures.append(failure)
            write_progress(progress, selected, rows, failures)


if __name__ == "__main__":
    main()
