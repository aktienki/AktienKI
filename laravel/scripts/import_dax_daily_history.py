#!/usr/bin/env python3
"""Persist the longest available daily history for verified DAX members."""

from datetime import datetime, timedelta, timezone

from app.database.connection import Database
from app.market.import_service import MarketImportService
from app.market.provider_factory import build_training_market_data_provider
from app.repositories.instrument_repository import InstrumentRepository
from app.repositories.market_data_repository import MarketDataRepository
from app.repositories.technical_indicator_repository import TechnicalIndicatorRepository


HISTORY_YEARS = 30
RETENTION_DAYS = HISTORY_YEARS * 366


def main() -> int:
    database = Database()
    rows = database.fetch_all(
        """
        SELECT instrument.symbol
        FROM index_memberships membership
        JOIN market_indices market_index ON market_index.id = membership.market_index_id
        JOIN instruments instrument ON instrument.id = membership.instrument_id
        WHERE market_index.symbol = '^GDAXI'
          AND membership.removed_at IS NULL
          AND instrument.is_active = TRUE
          AND instrument.deleted_at IS NULL
        ORDER BY instrument.symbol
        """
    )
    service = MarketImportService(
        InstrumentRepository(database),
        MarketDataRepository(database),
        build_training_market_data_provider(),
        TechnicalIndicatorRepository(database),
    )
    start = datetime.now(timezone.utc) - timedelta(days=RETENTION_DAYS)
    failures: list[str] = []
    for position, row in enumerate(rows, start=1):
        symbol = str(row["symbol"])
        try:
            result = service.import_symbol(
                symbol,
                timeframe="1d",
                start=start,
                persist=True,
                persistence_purpose="technical_indicator_history",
                retention_days=RETENTION_DAYS,
            )
            database.commit()
            print(
                f"DAX_HISTORY_PROGRESS {position}/{len(rows)} {symbol} "
                f"fetched={result.fetched} written={result.written} skipped={result.skipped}",
                flush=True,
            )
        except Exception as exc:
            database.rollback()
            failures.append(symbol)
            print(f"DAX_HISTORY_FAILED {symbol}: {exc}", flush=True)
    database.close()
    print(
        f"DAX_HISTORY_COMPLETE total={len(rows)} failed={len(failures)} "
        f"symbols={','.join(failures) if failures else '-'}",
        flush=True,
    )
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
