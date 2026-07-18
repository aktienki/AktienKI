from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any

from app.core.market_importer import MarketImporter
from app.providers.yahoo_provider import YahooProvider
from app.repositories.strategy_profile_repository import (
    StrategyProfileRepository,
)

logger = logging.getLogger(__name__)


class CrossAssetImportEngine:
    """
    Importiert alle Cross-Asset-Instrumente eines Strategy Profiles.

    Sprint 17:
    - Laufstatistik
    - Laufzeitmessung
    - saubere Fehlerbehandlung
    - Vorbereitung für Scheduler
    """

    def __init__(
        self,
        session_factory,
        *,
        yahoo_timeout_seconds: int,
        batch_size: int,
    ) -> None:
        self.session_factory = session_factory
        self.yahoo_timeout_seconds = yahoo_timeout_seconds
        self.batch_size = batch_size

    def run(
        self,
        *,
        strategy_code: str,
        full: bool = False,
    ) -> dict[str, Any]:

        started = datetime.now(timezone.utc)

        with self.session_factory() as session:
            strategy = StrategyProfileRepository(
                session
            ).get_by_code(strategy_code)

        if strategy is None:
            raise RuntimeError(
                f"Strategy '{strategy_code}' nicht gefunden."
            )

        importer = MarketImporter(
            self.session_factory,
            YahooProvider(
                timeout_seconds=self.yahoo_timeout_seconds,
            ),
            batch_size=self.batch_size,
        )

        results: list[dict[str, Any]] = []

        completed = 0
        failed = 0
        bars_written = 0

        for instrument in strategy.instruments:

            logger.info(
                "Importiere %s (%s)",
                instrument.alias,
                instrument.role,
            )

            try:

                stats = importer.run(
                    interval=strategy.interval,
                    period=f"{strategy.history_years}y",
                    instrument_id=instrument.instrument_id,
                    full=full,
                )

                completed += 1
                bars_written += stats.get(
                    "bars_written",
                    0,
                )

                results.append(
                    {
                        "instrument_id": instrument.instrument_id,
                        "symbol": instrument.alias,
                        "role": instrument.role,
                        "status": "completed",
                        "stats": stats,
                    }
                )

            except Exception as ex:

                failed += 1

                logger.exception(
                    "Import fehlgeschlagen: %s",
                    instrument.alias,
                )

                results.append(
                    {
                        "instrument_id": instrument.instrument_id,
                        "symbol": instrument.alias,
                        "role": instrument.role,
                        "status": "failed",
                        "error": str(ex),
                    }
                )

        finished = datetime.now(timezone.utc)

        runtime = (
            finished - started
        ).total_seconds()

        summary = {
            "strategy": strategy.code,
            "version": strategy.version,
            "started": started.isoformat(),
            "finished": finished.isoformat(),
            "runtime_seconds": round(runtime, 2),
            "status": (
                "completed"
                if failed == 0
                else "completed_with_errors"
            ),
            "total": len(results),
            "completed": completed,
            "failed": failed,
            "bars_written": bars_written,
            "results": results,
        }

        logger.info(
            "Cross Asset Import beendet (%s/%s erfolgreich)",
            completed,
            len(results),
        )

        return summary