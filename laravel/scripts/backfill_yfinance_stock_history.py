from __future__ import annotations

import argparse
import os
import sys
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path


def engine_root() -> Path:
    configured = os.environ.get("AKTIENKI_PYTHON_ENGINE_PATH")
    return Path(configured).expanduser().resolve() if configured else Path.home() / "Downloads" / "python-engine"


ENGINE_ROOT = engine_root()
sys.path.insert(0, str(ENGINE_ROOT))

import pandas as pd  # noqa: E402
import yfinance as yf  # noqa: E402
from app.database.connection import Database  # noqa: E402


def chunks(items: list[dict], size: int):
    for start in range(0, len(items), size):
        yield items[start : start + size]


def scalar(value):
    if isinstance(value, pd.Series):
        value = value.iloc[0] if not value.empty else None
    if value is None or pd.isna(value):
        return None
    return float(value)


def frame_for_symbol(data: pd.DataFrame, symbol: str, batch_size: int) -> pd.DataFrame:
    if data is None or data.empty:
        return pd.DataFrame()
    if not isinstance(data.columns, pd.MultiIndex):
        return data if batch_size == 1 else pd.DataFrame()
    first_level = data.columns.get_level_values(0)
    last_level = data.columns.get_level_values(-1)
    if symbol in first_level:
        return data[symbol]
    if symbol in last_level:
        return data.xs(symbol, axis=1, level=-1, drop_level=True)
    return pd.DataFrame()


def database_rows(instrument: dict, frame: pd.DataFrame) -> list[tuple]:
    now = datetime.now(timezone.utc)
    rows = []
    for index, values in frame.iterrows():
        open_value = scalar(values.get("Open"))
        high_value = scalar(values.get("High"))
        low_value = scalar(values.get("Low"))
        close_value = scalar(values.get("Close"))
        if None in (open_value, high_value, low_value, close_value):
            continue
        adjusted = scalar(values.get("Adj Close")) or close_value
        volume = scalar(values.get("Volume"))
        timestamp = index.to_pydatetime() if hasattr(index, "to_pydatetime") else index
        if timestamp.tzinfo is None:
            timestamp = timestamp.replace(tzinfo=timezone.utc)
        else:
            timestamp = timestamp.astimezone(timezone.utc)
        rows.append((
            int(instrument["id"]), "1d", timestamp, open_value, high_value,
            low_value, close_value, adjusted, volume, "yfinance", now, now,
        ))
    return rows


def run(years: int, batch_size: int, pause: float, force: bool, symbols: list[str] | None = None) -> dict:
    cutoff = datetime.now(timezone.utc) - timedelta(days=365 * years)
    fresh_after = datetime.now(timezone.utc) - timedelta(days=8)
    start = (cutoff - timedelta(days=10)).date().isoformat()
    end = (datetime.now(timezone.utc) + timedelta(days=1)).date().isoformat()

    with Database() as database:
        instruments = database.fetch_all(
            """SELECT id, symbol, COALESCE(NULLIF(provider_symbol, ''), symbol) AS provider_symbol
               FROM instruments
               WHERE type='stock' AND is_active=TRUE AND deleted_at IS NULL
               ORDER BY id"""
        )
        if symbols:
            requested = {symbol.strip().upper() for symbol in symbols if symbol.strip()}
            instruments = [
                instrument for instrument in instruments
                if str(instrument["symbol"]).upper() in requested
                or str(instrument["provider_symbol"]).upper() in requested
            ]
        coverage = database.fetch_all(
            """SELECT instrument_id, COUNT(*) AS bars, MIN(bar_time) AS first_bar, MAX(bar_time) AS last_bar
               FROM price_bars
               WHERE interval='1d' AND instrument_id = ANY(%s)
               GROUP BY instrument_id""",
            ([int(row["id"]) for row in instruments],),
        )

    coverage_by_id = {int(row["instrument_id"]): row for row in coverage}
    pending = []
    skipped = 0
    for instrument in instruments:
        existing = coverage_by_id.get(int(instrument["id"]))
        complete = bool(
            existing
            and existing["first_bar"] <= cutoff + timedelta(days=10)
            and existing["last_bar"] >= fresh_after
            and int(existing["bars"]) >= years * 220
        )
        if complete and not force:
            skipped += 1
        else:
            pending.append(instrument)

    print(f"YFINANCE_BACKFILL total={len(instruments)} pending={len(pending)} skipped={skipped}", flush=True)
    stored = failed = bars_written = 0
    with Database() as database:
        for number, batch in enumerate(chunks(pending, batch_size), start=1):
            symbols = [str(row["provider_symbol"]) for row in batch]
            try:
                data = yf.download(
                    tickers=symbols, start=start, end=end, interval="1d",
                    auto_adjust=False, actions=False, progress=False,
                    threads=True, group_by="ticker", timeout=45,
                )
            except Exception as exception:
                print(f"YFINANCE_BATCH_ERROR batch={number} error={exception}", flush=True)
                data = pd.DataFrame()

            for instrument in batch:
                symbol = str(instrument["provider_symbol"])
                frame = frame_for_symbol(data, symbol, len(batch))
                rows = database_rows(instrument, frame)
                if not rows:
                    failed += 1
                    print(f"YFINANCE_MISSING instrument={instrument['symbol']} provider={symbol}", flush=True)
                    continue
                with database.cursor() as cursor:
                    cursor.executemany(
                        """INSERT INTO price_bars (
                               instrument_id, interval, bar_time, open, high, low, close,
                               adjusted_close, volume, source, created_at, updated_at
                           ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                           ON CONFLICT (instrument_id, interval, bar_time) DO UPDATE SET
                               open=EXCLUDED.open, high=EXCLUDED.high, low=EXCLUDED.low,
                               close=EXCLUDED.close, adjusted_close=EXCLUDED.adjusted_close,
                               volume=EXCLUDED.volume, source=EXCLUDED.source,
                               updated_at=EXCLUDED.updated_at""",
                        rows,
                    )
                database.commit()
                stored += 1
                bars_written += len(rows)

            print(
                f"YFINANCE_PROGRESS processed={min(number * batch_size, len(pending))}/{len(pending)} "
                f"stored={stored} failed={failed} bars={bars_written}",
                flush=True,
            )
            if pause > 0:
                time.sleep(pause)

    return {
        "total": len(instruments), "pending": len(pending), "skipped": skipped,
        "stored": stored, "failed": failed, "bars_written": bars_written,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--years", type=int, default=3)
    parser.add_argument("--batch-size", type=int, default=20)
    parser.add_argument("--pause", type=float, default=1.0)
    parser.add_argument("--force", action="store_true")
    parser.add_argument("--symbols", nargs="*", default=None)
    args = parser.parse_args()
    result = run(
        max(1, min(10, args.years)),
        max(1, min(50, args.batch_size)),
        max(0, args.pause),
        args.force,
        args.symbols,
    )
    print(result, flush=True)
    return 0 if result["failed"] == 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())
