#!/usr/bin/env python3
"""Persist up to 30 years of daily history for all configured market indices."""

from datetime import datetime, timedelta, timezone
import sys

from app.database.connection import Database
from app.market.import_service import MarketImportService
from app.market.provider_factory import build_training_market_data_provider
from app.repositories.instrument_repository import InstrumentRepository
from app.repositories.market_data_repository import MarketDataRepository


RETENTION_DAYS = 30 * 366


def main() -> int:
    database = Database()
    indices = database.fetch_all(
        """SELECT symbol,name,country,currency
             FROM market_indices WHERE is_active=TRUE
             ORDER BY global_rank NULLS LAST,name"""
    )
    requested = {symbol.upper() for symbol in sys.argv[1:]}
    if requested:
        indices = [row for row in indices if str(row["symbol"]).upper() in requested]
    for index in indices:
        database.execute(
            """
            INSERT INTO instruments
                (type,symbol,provider_symbol,name,short_name,country,currency,
                 is_active,is_tradeable,meta,created_at,updated_at)
            SELECT 'index',%s,%s,%s,%s,%s,%s,TRUE,FALSE,
                   json_build_object('source','market_indices'),NOW(),NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM instruments
                WHERE symbol=%s AND type='index' AND deleted_at IS NULL
            )
            """,
            (
                index["symbol"], index["symbol"], index["name"], index["name"],
                index["country"], index["currency"], index["symbol"],
            ),
        )
    database.commit()

    service = MarketImportService(
        InstrumentRepository(database),
        MarketDataRepository(database),
        build_training_market_data_provider(),
    )
    start = datetime.now(timezone.utc) - timedelta(days=RETENTION_DAYS)
    failures: list[str] = []
    empty: list[str] = []
    for position, index in enumerate(indices, start=1):
        symbol = str(index["symbol"])
        try:
            result = service.import_symbol(
                symbol,
                timeframe="1d",
                start=start,
                persist=True,
                persistence_purpose="prediction_validation",
                retention_days=RETENTION_DAYS,
            )
            database.commit()
            if result.fetched == 0:
                empty.append(symbol)
            print(
                f"INDEX_HISTORY_PROGRESS {position}/{len(indices)} {symbol} "
                f"fetched={result.fetched} written={result.written} skipped={result.skipped}",
                flush=True,
            )
        except Exception as exc:
            database.rollback()
            failures.append(symbol)
            print(f"INDEX_HISTORY_FAILED {symbol}: {exc}", flush=True)
    database.close()
    print(
        f"INDEX_HISTORY_COMPLETE total={len(indices)} failed={len(failures)} "
        f"empty={len(empty)} failure_symbols={','.join(failures) or '-'} "
        f"empty_symbols={','.join(empty) or '-'}",
        flush=True,
    )
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
