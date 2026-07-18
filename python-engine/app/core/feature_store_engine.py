import logging

import pandas as pd

from app.features.feature_builder import FeatureBuilder
from app.repositories.feature_input_repository import FeatureInputRepository
from app.repositories.feature_store_writer_repository import (
    FeatureStoreWriterRepository,
)
from app.repositories.instrument_repository import InstrumentRepository

logger = logging.getLogger(__name__)


class FeatureStoreEngine:
    def __init__(
        self,
        session_factory,
        *,
        batch_size: int = 1000,
    ):
        self.session_factory = session_factory
        self.batch_size = batch_size
        self.builder = FeatureBuilder()

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
            "empty": 0,
            "rows_written": 0,
        }

        for position, instrument in enumerate(instruments, start=1):
            label = (
                f"[{position}/{len(instruments)}] "
                f"{instrument.provider_symbol}"
            )

            try:
                count = self.build_one(
                    instrument_id=instrument.id,
                    interval=interval,
                )

                stats["completed"] += 1
                stats["rows_written"] += count

                if count == 0:
                    stats["empty"] += 1
                    logger.warning(
                        "%s: keine Feature-Zeilen erzeugt",
                        label,
                    )
                else:
                    logger.info(
                        "%s: %d Feature-Zeilen gespeichert",
                        label,
                        count,
                    )

            except Exception:
                stats["failed"] += 1
                logger.exception(
                    "%s: Feature Store fehlgeschlagen",
                    label,
                )

        return stats

    def build_one(
        self,
        *,
        instrument_id: int,
        interval: str,
    ) -> int:
        with self.session_factory() as session:
            frame = FeatureInputRepository(session).load(
                instrument_id=instrument_id,
                interval=interval,
            )

        if frame.empty:
            return 0

        built = self.builder.build(frame)

        print("=" * 80)
        print("INPUT :", frame.shape)
        print("OUTPUT:", built.shape)
        print(built.head())
        print("=" * 80)

        rows = self._to_rows(
            built,
            instrument_id=instrument_id,
            interval=interval,
        )


        if not rows:
            return 0

        with self.session_factory() as session:
            repository = FeatureStoreWriterRepository(session)

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

    @staticmethod
    def _nullable_float(value):
        return None if pd.isna(value) else float(value)

    def _to_rows(
        self,
        frame: pd.DataFrame,
        *,
        instrument_id: int,
        interval: str,
    ) -> list[dict]:
        rows: list[dict] = []

        for bar_time, values in frame.iterrows():
            close = values.get("Close")

            if pd.isna(close):
                continue

            rows.append(
                {
                    "instrument_id": instrument_id,
                    "interval": interval,
                    "bar_time": bar_time.to_pydatetime(),
                    "close": float(close),
                    "volume": self._nullable_float(
                        values.get("Volume")
                    ),

                    "rsi_14": self._nullable_float(
                        values.get("rsi_14")
                    ),
                    "ema_20": self._nullable_float(
                        values.get("ema_20")
                    ),
                    "ema_50": self._nullable_float(
                        values.get("ema_50")
                    ),
                    "ema_200": self._nullable_float(
                        values.get("ema_200")
                    ),
                    "macd": self._nullable_float(
                        values.get("macd")
                    ),
                    "atr_14": self._nullable_float(
                        values.get("atr_14")
                    ),
                    "volatility_20": self._nullable_float(
                        values.get("volatility_20")
                    ),
                    "target_return_1d": self._nullable_float(
                        values.get("target_return_1d")
                    ),
                    "target_return_5d": self._nullable_float(
                        values.get("target_return_5d")
                    ),
                    "target_return_20d": self._nullable_float(
                        values.get("target_return_20d")
                    ),
                    "target_direction": (
                        None
                        if pd.isna(values.get("target_direction"))
                        else int(values.get("target_direction"))
                    ),
                    "feature_version": FeatureBuilder.VERSION,
                }
            )

        return rows
