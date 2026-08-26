from __future__ import annotations

import argparse
import json
import subprocess
import sys
from datetime import UTC, datetime
from pathlib import Path


def summarize(path: Path) -> dict:
    payload = json.loads(path.read_text(encoding="utf-8"))
    variants = {}
    for name, result in payload.get("variants", {}).items():
        account = result.get("account_simulation", {})
        variants[name] = {
            key: account.get(key)
            for key in (
                "trades", "hit_rate", "profit_factor", "average_return_percent",
                "final_capital", "return_percent",
                "maximum_closed_trade_drawdown_percent", "capital_utilization_percent",
            )
        }
    baseline = variants.get("A_baseline", {})
    arima = variants.get("D_arima_timeseries", {})
    return {
        "symbol": payload.get("symbol"),
        "oos_years": payload.get("oos_years"),
        "variants": variants,
        "arima_return_uplift_pp": round(
            float(arima.get("return_percent") or 0) - float(baseline.get("return_percent") or 0), 3
        ),
        "arima_pf_uplift": round(
            float(arima.get("profit_factor") or 0) - float(baseline.get("profit_factor") or 0), 3
        ),
    }


def write_progress(path: Path, device: str, rows: list[dict], failures: list[dict]) -> None:
    path.write_text(
        json.dumps(
            {
                "device": device,
                "updated_at": datetime.now(UTC).isoformat(),
                "completed": len(rows),
                "failed": len(failures),
                "results": rows,
                "failures": failures,
            },
            indent=2,
            ensure_ascii=False,
        ) + "\n",
        encoding="utf-8",
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--symbols", required=True, help="Comma-separated symbols")
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--device", required=True)
    parser.add_argument("--years", type=int, default=10)
    args = parser.parse_args()

    args.output_dir.mkdir(parents=True, exist_ok=True)
    progress = args.output_dir / "progress.json"
    rows: list[dict] = []
    failures: list[dict] = []
    for raw_symbol in args.symbols.split(","):
        symbol = raw_symbol.strip()
        if not symbol:
            continue
        output = args.output_dir / f"{symbol}.json"
        command = [
            sys.executable,
            str(Path(__file__).with_name("test_timeseries_pytorch_meta_20t.py")),
            "--symbol", symbol,
            "--years", str(args.years),
            "--variants", "A_baseline,D_arima_timeseries",
            "--output", str(output),
        ]
        try:
            subprocess.run(command, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
            rows.append(summarize(output))
        except subprocess.CalledProcessError as error:
            failures.append({"symbol": symbol, "returncode": error.returncode, "error": error.stderr[-2000:]})
        write_progress(progress, args.device, rows, failures)


if __name__ == "__main__":
    main()
