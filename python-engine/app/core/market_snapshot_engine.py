from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any, Iterable

from app.core.market_registry import MarketRegistry
from app.repositories.market_snapshot_repository import (
    MarketSnapshotRepository,
)

logger = logging.getLogger(__name__)


class MarketSnapshotEngine:
    """
    Erstellt einen Snapshot des Gesamtmarktes.

    Die Engine sammelt globale Marktwerte aus dem MarketRegistry
    und speichert diese über das MarketSnapshotRepository.

    Instrumentenspezifische ML-Features werden hier nicht erzeugt.
    """

    MARKET_GROUPS = (
        ("volatility", "volatility"),
        ("interest_rates", "interest_rates"),
        ("currencies", "currencies"),
        ("commodities", "commodities"),
    )

    def __init__(
        self,
        session_factory,
        provider,
    ) -> None:
        self.session_factory = session_factory
        self.provider = provider

    def run(self) -> dict[str, Any]:
        """
        Erstellt und speichert einen neuen Market Snapshot.

        Fehler einzelner Symbole stoppen den gesamten Snapshot nicht.
        Datenbankfehler führen zu einem Rollback.
        """

        with self.session_factory() as session:
            try:
                registry = MarketRegistry(session)
                repository = MarketSnapshotRepository(session)

                snapshot = self._create_empty_snapshot()

                for category, registry_method_name in self.MARKET_GROUPS:
                    registry_method = getattr(
                        registry,
                        registry_method_name,
                    )

                    instruments = registry_method()

                    self._collect_quotes(
                        snapshot=snapshot,
                        instruments=instruments,
                        category=category,
                    )

                snapshot["feature_data"] = (
                    self._build_default_feature_data()
                )

                repository.insert(snapshot)
                session.commit()

                logger.info(
                    "Market Snapshot gespeichert: %s Marktwerte.",
                    len(snapshot["market_data"]),
                )

                return snapshot

            except Exception:
                session.rollback()

                logger.exception(
                    "Market Snapshot konnte nicht gespeichert werden."
                )

                raise

    @staticmethod
    def _create_empty_snapshot() -> dict[str, Any]:
        return {
            "snapshot_time": datetime.now(timezone.utc),
            "market_data": {},
            "feature_data": {},
        }

    def _collect_quotes(
        self,
        *,
        snapshot: dict[str, Any],
        instruments: Iterable[Any],
        category: str,
    ) -> None:
        """
        Lädt alle Instrumente einer Marktkategorie.

        Ein Providerfehler bei einem einzelnen Symbol wird protokolliert.
        Die übrigen Symbole werden trotzdem weiterverarbeitet.
        """

        for instrument in instruments:
            provider_symbol = getattr(
                instrument,
                "provider_symbol",
                None,
            )

            internal_symbol = getattr(
                instrument,
                "symbol",
                provider_symbol,
            )

            if not provider_symbol:
                logger.warning(
                    "Instrument ohne Provider-Symbol übersprungen: %s",
                    internal_symbol,
                )
                continue

            try:
                quote = self.provider.quote(provider_symbol)

                price = self._extract_price(quote)

                if price is None:
                    logger.warning(
                        "Kein Marktpreis erhalten: %s (%s, %s)",
                        internal_symbol,
                        provider_symbol,
                        category,
                    )
                    continue

                snapshot["market_data"][internal_symbol] = price

                logger.debug(
                    "Marktwert geladen: %s=%s (%s)",
                    internal_symbol,
                    price,
                    category,
                )

            except Exception as exception:
                logger.warning(
                    "Marktwert konnte nicht geladen werden: "
                    "%s (%s, %s): %s",
                    internal_symbol,
                    provider_symbol,
                    category,
                    exception,
                    exc_info=True,
                )

    @staticmethod
    def _extract_price(
        quote: Any,
    ) -> float | None:
        """
        Extrahiert den aktuellen Kurs aus der Providerantwort.

        Unterstützt Dictionary-Antworten sowie direkte Zahlenwerte.
        """

        if quote is None:
            return None

        if isinstance(quote, dict):
            value = (
                quote.get("regularMarketPrice")
                or quote.get("currentPrice")
                or quote.get("price")
                or quote.get("previousClose")
            )
        else:
            value = quote

        if value is None:
            return None

        try:
            return float(value)
        except (TypeError, ValueError):
            return None

    @staticmethod
    def _build_default_feature_data() -> dict[str, float]:
        """
        Platzhalterwerte für den späteren MarketFeatureBuilder.
        """

        return {
            "market_bull_score": 0.0,
            "market_bear_score": 0.0,
            "market_volatility_score": 0.0,
            "market_liquidity_score": 0.0,
            "market_risk_score": 0.0,
            "market_momentum_score": 0.0,
        }