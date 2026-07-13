from __future__ import annotations

import logging

from app.core.market_importer import MarketImporter
from app.providers.yahoo_provider import YahooProvider
from app.repositories.strategy_profile_repository import (
    StrategyProfileRepository,
)

logger = logging.getLogger(__name__)


class CrossAssetImportEngine:
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
    ) -> dict:
        with self.session_factory() as session:
            strategy = StrategyProfileRepository(
                session
            ).get_by_code(strategy_code)

        if strategy is None:
            raise RuntimeError(
                f"Strategy Profile '{strategy_code}' wurde nicht gefunden."
            )

        importer = MarketImporter(
            self.session_factory,
            YahooProvider(self.yahoo_timeout_seconds),
            batch_size=self.batch_size,
        )

        results = []

        for instrument in strategy.instruments:
            try:
                stats = importer.run(
                    interval=strategy.interval,
                    period=f"{strategy.history_years}y",
                    instrument_id=instrument.instrument_id,
                    full=full,
                )

                results.append(
                    {
                        "instrument_id": instrument.instrument_id,
                        "alias": instrument.alias,
                        "role": instrument.role,
                        "status": "completed",
                        "result": stats,
                    }
                )

            except Exception as exception:
                logger.exception(
                    "Cross-Asset-Import für %s fehlgeschlagen.",
                    instrument.alias,
                )

                results.append(
                    {
                        "instrument_id": instrument.instrument_id,
                        "alias": instrument.alias,
                        "role": instrument.role,
                        "status": "failed",
                        "error": str(exception),
                    }
                )

        failed = [
            item
            for item in results
            if item["status"] == "failed"
        ]

        return {
            "strategy_code": strategy.code,
            "strategy_version": strategy.version,
            "status": "failed" if failed else "completed",
            "instruments_total": len(results),
            "instruments_failed": len(failed),
            "results": results,
        }
