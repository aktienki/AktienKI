from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import UTC, datetime
from pathlib import Path

from app.database import Database


HORIZONS = (5, 10, 15, 20)


def candidates(limit: int) -> list[dict]:
    database = Database()
    try:
        return [
            dict(row)
            for row in database.fetch_all(
                """
                SELECT i.id,i.symbol,i.name,i.market_cap,
                  count(DISTINCT tm.prediction_horizon_minutes) FILTER (
                    WHERE tm.deleted_at IS NULL AND tm.status='active'
                      AND tm.ai_type='horizon' AND tm.timeframe='1d'
                      AND tm.prediction_horizon_minutes IN (7200,14400,21600,28800)
                  ) AS active_horizons
                FROM instruments i
                LEFT JOIN trained_models tm ON tm.instrument_id=i.id
                WHERE i.deleted_at IS NULL AND lower(i.type)='stock'
                  AND EXISTS (
                    SELECT 1 FROM price_bars b
                    WHERE b.instrument_id=i.id AND b.interval='1d'
                    GROUP BY b.instrument_id
                    HAVING COUNT(*)>=80
                       AND MAX(b.bar_time)-MIN(b.bar_time)>=INTERVAL '1000 days'
                  )
                GROUP BY i.id,i.symbol,i.name,i.market_cap
                HAVING count(DISTINCT tm.prediction_horizon_minutes) FILTER (
                    WHERE tm.deleted_at IS NULL AND tm.status='active'
                      AND tm.ai_type='horizon' AND tm.timeframe='1d'
                      AND tm.prediction_horizon_minutes IN (7200,14400,21600,28800)
                ) < 4
                ORDER BY active_horizons ASC,i.market_cap DESC NULLS LAST,i.symbol
                LIMIT %s
                """,
                (limit,),
            )
        ]
    finally:
        database.close()


def write_progress(path: Path, selected: list[dict], completed: list[dict], failed: list[dict]) -> None:
    path.write_text(
        json.dumps(
            {
                "pipeline": "standard_horizon_without_arima",
                "feature_profile": "triple_daily_macro_v1",
                "horizons": list(HORIZONS),
                "updated_at": datetime.now(UTC).isoformat(),
                "selected": len(selected),
                "completed": len(completed),
                "failed": len(failed),
                "remaining": len(selected) - len(completed) - len(failed),
                "results": completed,
                "failures": failed,
            },
            ensure_ascii=False,
            indent=2,
            default=str,
        )
        + "\n",
        encoding="utf-8",
    )


def run_stock(stock: dict, project: Path) -> tuple[dict | None, dict | None]:
    symbol = str(stock["symbol"])
    started = datetime.now(UTC)
    env = os.environ.copy()
    env.update(
        {
            "TRAINING_YEARS": "30",
            "OMP_NUM_THREADS": "4",
            "OPENBLAS_NUM_THREADS": "4",
            "MKL_NUM_THREADS": "4",
        }
    )
    stages: list[dict] = []
    commands = [
        (
            "training",
            [
                str(project / ".venv/bin/aktienki-engine"),
                "train-predict",
                "--symbol",
                symbol,
                "--benchmark",
                "auto",
                "--timeframe",
                "1d",
                "--horizons",
                *[str(value) for value in HORIZONS],
                "--training-only",
                "--minimum-historical-hit-rate",
                "0.55",
                "--minimum-profit-factor",
                "1.3",
                "--minimum-validation-trades",
                "10",
                "--maximum-drawdown",
                "0.40",
                "--position-side",
                "long",
            ],
        ),
        *[
            (
                f"walk_forward_{horizon}d",
                [
                    str(project / ".venv/bin/python"),
                    "-m",
                    "app.cli.backtest_walk_forward_heatmap",
                    "--years",
                    "3",
                    "--history-years",
                    "30",
                    "--horizon",
                    str(horizon),
                    "--buy-threshold",
                    "0.01",
                    "--transaction-cost",
                    "0.005",
                    "--position-side",
                    "long",
                    "--symbols",
                    symbol,
                ],
            )
            for horizon in HORIZONS
        ],
    ]
    for stage, command in commands:
        stage_started = datetime.now(UTC)
        result = subprocess.run(
            command,
            cwd=project,
            env=env,
            text=True,
            capture_output=True,
            timeout=10_800 if stage == "training" else 3_600,
            check=False,
        )
        stages.append(
            {
                "stage": stage,
                "exit_code": result.returncode,
                "duration_seconds": round((datetime.now(UTC) - stage_started).total_seconds(), 2),
                "output_tail": result.stdout[-1500:],
                "error_tail": result.stderr[-1500:],
            }
        )
        if result.returncode != 0:
            return None, {
                "symbol": symbol,
                "name": stock.get("name"),
                "failed_stage": stage,
                "duration_seconds": round((datetime.now(UTC) - started).total_seconds(), 2),
                "stages": stages,
            }
    return {
        "symbol": symbol,
        "name": stock.get("name"),
        "duration_seconds": round((datetime.now(UTC) - started).total_seconds(), 2),
        "stages": stages,
    }, None


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, default=60)
    parser.add_argument("--workers", type=int, default=2)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()

    project = Path(__file__).resolve().parents[1]
    selected = candidates(args.limit)
    completed: list[dict] = []
    failed: list[dict] = []
    args.output.parent.mkdir(parents=True, exist_ok=True)
    write_progress(args.output, selected, completed, failed)
    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as executor:
        futures = [executor.submit(run_stock, stock, project) for stock in selected]
        for future in as_completed(futures):
            result, failure = future.result()
            if result is not None:
                completed.append(result)
            if failure is not None:
                failed.append(failure)
            write_progress(args.output, selected, completed, failed)


if __name__ == "__main__":
    main()
