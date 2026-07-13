import logging

import pandas as pd

from app.features.indicators import IndicatorCalculator
from app.repositories.indicator_repository import IndicatorRepository
from app.repositories.instrument_repository import InstrumentRepository
from app.repositories.price_history_repository import PriceHistoryRepository

logger = logging.getLogger(__name__)


class IndicatorEngine:
    OUTPUT_COLUMNS = [
        "sma_20",
        "sma_50",
        "sma_200",
        "ema_12",
        "ema_20",
        "ema_26",
        "ema_50",
        "ema_200",
        "rsi_14",
        "macd",
        "macd_signal",
        "macd_histogram",
        "atr_14",
        "adx_14",
        "bollinger_upper",
        "bollinger_middle",
        "bollinger_lower",
        "bollinger_width",
        "stochastic_k",
        "stochastic_d",
        "roc_12",
        "momentum_10",
        "volatility_20",
        "volume_sma_20",
    ]

    def __init__(self, session_factory, *, batch_size: int = 1000):
        self.session_factory = session_factory
        self.batch_size = batch_size

    def run(
        self,
        *,
        interval: str = "1d",
        types: list[str] | None = None,
        limit: int | None = None,
        symbol: str | None = None,
        instrument_id: int | None = None,
    ) -> dict[str, int]:
        with self.session_factory() as session:
            instruments = InstrumentRepository(session).active(
                types=types,
                limit=limit,
                symbol=symbol,
                instrument_id=instrument_id,
            )

        stats = {
            "total": len(instruments),
            "completed": 0,
            "failed": 0,
            "rows_written": 0,
            "empty": 0,
        }

        for position, instrument in enumerate(instruments, start=1):
            label = f"[{position}/{len(instruments)}] {instrument.provider_symbol}"

            try:
                count = self.calculate_one(
                    instrument_id=instrument.id,
                    interval=interval,
                )

                stats["completed"] += 1
                stats["rows_written"] += count

                if count == 0:
                    stats["empty"] += 1
                    logger.warning("%s: keine Indikatoren erzeugt", label)
                else:
                    logger.info("%s: %d Indikatorzeilen gespeichert", label, count)

            except Exception:
                stats["failed"] += 1
                logger.exception("%s: Indikatorberechnung fehlgeschlagen", label)

        return stats

    def calculate_one(self, *, instrument_id: int, interval: str) -> int:
        with self.session_factory() as session:
            frame = PriceHistoryRepository(session).load(
                instrument_id,
                interval,
            )

        if frame.empty:
            return 0

        calculated = IndicatorCalculator.calculate(frame)
        rows = self._to_rows(
            calculated,
            instrument_id=instrument_id,
            interval=interval,
        )

        if not rows:
            return 0

        with self.session_factory() as session:
            repository = IndicatorRepository(session)

            try:
                written = repository.upsert_many(
                    rows,
                    batch_size=self.batch_size,
                )
                session.commit()
                return written
            except Exception:
                session.rollback()
                raise

    def _to_rows(
        self,
        frame: pd.DataFrame,
        *,
        instrument_id: int,
        interval: str,
    ) -> list[dict]:
        rows: list[dict] = []

        for bar_time, values in frame.iterrows():
            payload = {
                "instrument_id": instrument_id,
                "interval": interval,
                "bar_time": bar_time.to_pydatetime(),
                "calculation_version": IndicatorCalculator.VERSION,
            }

            has_value = False

            for column in self.OUTPUT_COLUMNS:
                value = values.get(column)

                if pd.isna(value):
                    payload[column] = None
                else:
                    payload[column] = float(value)
                    has_value = True

            if has_value:
                rows.append(payload)

        return rows
