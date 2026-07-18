from __future__ import annotations

import logging
import time
from dataclasses import asdict, dataclass
from datetime import timedelta
from decimal import Decimal, InvalidOperation
from typing import Any, Callable, Iterable, TypeVar

import pandas as pd

from app.models.market import PriceBar
from app.repositories.instrument_repository import InstrumentRepository
from app.repositories.price_bar_repository import PriceBarRepository

logger = logging.getLogger(__name__)

T = TypeVar("T")


@dataclass(slots=True)
class ImportStatus:
    total: int = 0
    completed: int = 0
    failed: int = 0
    empty: int = 0
    bars_written: int = 0

    def mark_success(self, bars_written: int) -> None:
        self.completed += 1
        self.bars_written += bars_written
        if bars_written == 0:
            self.empty += 1

    def mark_failure(self) -> None:
        self.failed += 1

    def to_dict(self) -> dict[str, int]:
        return asdict(self)


def to_decimal(value: Any) -> Decimal | None:
    """Convert provider values safely to Decimal."""
    if value is None or pd.isna(value):
        return None

    try:
        return Decimal(str(value))
    except (InvalidOperation, TypeError, ValueError):
        return None


class MarketImporter:
    REQUIRED_COLUMNS = frozenset({"Open", "High", "Low", "Close"})

    def __init__(
        self,
        session_factory,
        provider,
        *,
        batch_size: int,
        max_retries: int = 3,
        retry_delay_seconds: float = 1.0,
    ) -> None:
        if batch_size < 1:
            raise ValueError("batch_size muss mindestens 1 sein")
        if max_retries < 1:
            raise ValueError("max_retries muss mindestens 1 sein")
        if retry_delay_seconds < 0:
            raise ValueError("retry_delay_seconds darf nicht negativ sein")

        self.session_factory = session_factory
        self.provider = provider
        self.batch_size = batch_size
        self.max_retries = max_retries
        self.retry_delay_seconds = retry_delay_seconds

    def run(
        self,
        *,
        interval: str = "1d",
        period: str = "10y",
        types: Iterable[str] | None = None,
        limit: int | None = None,
        symbol: str | None = None,
        instrument_id: int | None = None,
        full: bool = False,
    ) -> dict[str, int]:
        targets = self._load_targets(
            types=types,
            limit=limit,
            symbol=symbol,
            instrument_id=instrument_id,
        )
        status = ImportStatus(total=len(targets))

        for position, instrument in enumerate(targets, start=1):
            provider_symbol = str(instrument.provider_symbol)
            label = f"[{position}/{status.total}] {provider_symbol}"

            try:
                count = self.import_one(
                    instrument,
                    interval=interval,
                    period=period,
                    full=full,
                )
                status.mark_success(count)

                if count == 0:
                    logger.warning("%s: keine neuen Daten", label)
                else:
                    logger.info("%s: %d Bars gespeichert", label, count)
            except Exception:
                status.mark_failure()
                logger.exception("%s: Import fehlgeschlagen", label)

        return status.to_dict()

    def import_one(
        self,
        instrument,
        *,
        interval: str,
        period: str,
        full: bool,
    ) -> int:
        latest = self._latest_time(
            instrument_id=instrument.id,
            interval=interval,
            full=full,
        )
        start = latest - timedelta(days=2) if latest is not None else None

        frame = self._with_retry(
            lambda: self.provider.history(
                instrument.provider_symbol,
                interval=interval,
                period=period,
                start=start,
            ),
            operation=f"history({instrument.provider_symbol})",
        )

        bars = self.frame_to_bars(
            frame,
            instrument_id=instrument.id,
            interval=interval,
        )
        if not bars:
            return 0

        return self._store_bars(bars)

    def _load_targets(
        self,
        *,
        types: Iterable[str] | None,
        limit: int | None,
        symbol: str | None,
        instrument_id: int | None,
    ) -> list[Any]:
        with self.session_factory() as session:
            targets = InstrumentRepository(session).active(
                types=types,
                limit=limit,
                symbol=symbol,
                instrument_id=instrument_id,
            )
            return list(targets)

    def _latest_time(
        self,
        *,
        instrument_id: int,
        interval: str,
        full: bool,
    ):
        if full:
            return None

        with self.session_factory() as session:
            return PriceBarRepository(session).latest_time(
                instrument_id,
                interval,
            )

    def _store_bars(self, bars: list[PriceBar]) -> int:
        with self.session_factory() as session:
            repository = PriceBarRepository(session)
            try:
                count = repository.upsert_many(
                    bars,
                    batch_size=self.batch_size,
                )
                session.commit()
                return count
            except Exception:
                session.rollback()
                raise

    def _with_retry(
        self,
        callback: Callable[[], T],
        *,
        operation: str = "provider request",
    ) -> T:
        last_error: Exception | None = None

        for attempt in range(1, self.max_retries + 1):
            try:
                return callback()
            except Exception as error:
                last_error = error
                if attempt >= self.max_retries:
                    break

                delay = self.retry_delay_seconds * (2 ** (attempt - 1))
                logger.warning(
                    "%s fehlgeschlagen (Versuch %d/%d): %s; Retry in %.1fs",
                    operation,
                    attempt,
                    self.max_retries,
                    error,
                    delay,
                )
                if delay > 0:
                    time.sleep(delay)

        assert last_error is not None
        raise last_error

    @staticmethod
    def frame_to_bars(
        frame: pd.DataFrame | None,
        *,
        instrument_id: int,
        interval: str,
    ) -> list[PriceBar]:
        if frame is None or frame.empty:
            return []
        if not MarketImporter.REQUIRED_COLUMNS.issubset(frame.columns):
            return []

        bars: list[PriceBar] = []

        for index, row in frame.iterrows():
            open_price = to_decimal(row.get("Open"))
            high_price = to_decimal(row.get("High"))
            low_price = to_decimal(row.get("Low"))
            close_price = to_decimal(row.get("Close"))

            if any(
                value is None
                for value in (open_price, high_price, low_price, close_price)
            ):
                continue

            assert open_price is not None
            assert high_price is not None
            assert low_price is not None
            assert close_price is not None

            if min(open_price, high_price, low_price, close_price) < 0:
                continue
            if high_price < max(open_price, low_price, close_price):
                continue
            if low_price > min(open_price, high_price, close_price):
                continue

            try:
                timestamp = pd.Timestamp(index)
                if pd.isna(timestamp):
                    continue
                timestamp = (
                    timestamp.tz_localize("UTC")
                    if timestamp.tzinfo is None
                    else timestamp.tz_convert("UTC")
                )
            except (TypeError, ValueError, OverflowError):
                continue

            bars.append(
                PriceBar(
                    instrument_id=instrument_id,
                    interval=interval,
                    bar_time=timestamp.to_pydatetime(),
                    open=open_price,
                    high=high_price,
                    low=low_price,
                    close=close_price,
                    adjusted_close=to_decimal(row.get("Adj Close")),
                    volume=to_decimal(row.get("Volume")),
                )
            )

        return bars
