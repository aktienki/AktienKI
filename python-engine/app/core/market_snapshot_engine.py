from __future__ import annotations

import logging
from datetime import datetime, timezone

from app.core.market_registry import MarketRegistry
from app.repositories.market_snapshot_repository import (
    MarketSnapshotRepository,
)

logger = logging.getLogger(__name__)


class MarketSnapshotEngine:
    """
    Erstellt einen täglichen Snapshot des Gesamtmarktes.

    Die Engine erzeugt KEINE Instrumenten-Features.
    Sie beschreibt ausschließlich den Zustand des Gesamtmarktes.
    """

    def __init__(
        self,
        session_factory,
        provider,
    ):
        self.session_factory = session_factory
        self.provider = provider

    def run(self):

        with self.session_factory() as session:

            registry = MarketRegistry(session)

            repo = MarketSnapshotRepository(session)

            snapshot = {
                "snapshot_time": datetime.now(timezone.utc),

                "market_data": {},

                "feature_data": {},
            }

            #
            # Volatilität
            #
            for instrument in registry.volatility():

                quote = self.provider.quote(
                    instrument.provider_symbol
                )

                snapshot["market_data"][
                    instrument.symbol
                ] = quote.get("regularMarketPrice")

            #
            # Zinsen
            #
            for instrument in registry.interest_rates():

                quote = self.provider.quote(
                    instrument.provider_symbol
                )

                snapshot["market_data"][
                    instrument.symbol
                ] = quote.get("regularMarketPrice")

            #
            # Währungen
            #
            for instrument in registry.currencies():

                quote = self.provider.quote(
                    instrument.provider_symbol
                )

                snapshot["market_data"][
                    instrument.symbol
                ] = quote.get("regularMarketPrice")

            #
            # Rohstoffe
            #
            for instrument in registry.commodities():

                quote = self.provider.quote(
                    instrument.provider_symbol
                )

                snapshot["market_data"][
                    instrument.symbol
                ] = quote.get("regularMarketPrice")

            #
            # Platzhalter
            # werden später vom
            # MarketFeatureBuilder berechnet.
            #

            snapshot["feature_data"] = {
                "market_bull_score": 0.0,
                "market_bear_score": 0.0,
                "market_volatility_score": 0.0,
                "market_liquidity_score": 0.0,
                "market_risk_score": 0.0,
                "market_momentum_score": 0.0,
            }

            repo.insert(snapshot)

            session.commit()

            logger.info(
                "Market Snapshot gespeichert."
            )