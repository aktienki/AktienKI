import logging
from datetime import timedelta
from decimal import Decimal, InvalidOperation
import pandas as pd

from app.models.market import PriceBar
from app.repositories.instrument_repository import InstrumentRepository
from app.repositories.price_bar_repository import PriceBarRepository

logger = logging.getLogger(__name__)

def to_decimal(value):
    if value is None or pd.isna(value):
        return None
    try:
        return Decimal(str(value))
    except (InvalidOperation, TypeError, ValueError):
        return None

class MarketImporter:
    def __init__(self, session_factory, provider, *, batch_size):
        self.session_factory = session_factory
        self.provider = provider
        self.batch_size = batch_size

    def run(self, *, interval="1d", period="10y", types=None, limit=None, symbol=None, instrument_id=None, full=False):
        with self.session_factory() as session:
            targets = InstrumentRepository(session).active(
                types=types, limit=limit, symbol=symbol, instrument_id=instrument_id
            )

        stats = {"total": len(targets), "completed": 0, "failed": 0, "empty": 0, "bars_written": 0}

        for pos, instrument in enumerate(targets, 1):
            label = f"[{pos}/{len(targets)}] {instrument.provider_symbol}"
            try:
                count = self.import_one(instrument, interval=interval, period=period, full=full)
                stats["completed"] += 1
                stats["bars_written"] += count
                if count == 0:
                    stats["empty"] += 1
                    logger.warning("%s: keine Daten", label)
                else:
                    logger.info("%s: %d Bars gespeichert", label, count)
            except Exception:
                stats["failed"] += 1
                logger.exception("%s: Import fehlgeschlagen", label)

        return stats

    def import_one(self, instrument, *, interval, period, full):
        with self.session_factory() as session:
            repo = PriceBarRepository(session)
            latest = None if full else repo.latest_time(instrument.id, interval)

        start = latest - timedelta(days=2) if latest is not None else None
        frame = self.provider.history(
            instrument.provider_symbol,
            interval=interval,
            period=period,
            start=start,
        )
        bars = self.frame_to_bars(frame, instrument_id=instrument.id, interval=interval)
        if not bars:
            return 0

        with self.session_factory() as session:
            repo = PriceBarRepository(session)
            try:
                count = repo.upsert_many(bars, batch_size=self.batch_size)
                session.commit()
                return count
            except Exception:
                session.rollback()
                raise

    @staticmethod
    def frame_to_bars(frame, *, instrument_id, interval):
        required = {"Open", "High", "Low", "Close"}
        if frame.empty or not required.issubset(set(frame.columns)):
            return []

        bars = []
        for index, row in frame.iterrows():
            values = [to_decimal(row.get(k)) for k in ("Open", "High", "Low", "Close")]
            if any(v is None for v in values):
                continue

            ts = pd.Timestamp(index)
            ts = ts.tz_localize("UTC") if ts.tzinfo is None else ts.tz_convert("UTC")

            bars.append(PriceBar(
                instrument_id=instrument_id,
                interval=interval,
                bar_time=ts.to_pydatetime(),
                open=values[0],
                high=values[1],
                low=values[2],
                close=values[3],
                adjusted_close=to_decimal(row.get("Adj Close")),
                volume=to_decimal(row.get("Volume")),
            ))
        return bars
