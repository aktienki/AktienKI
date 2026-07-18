from __future__ import annotations

from typing import Any

from app.features.feature_builder import FeatureBuilder


class FeatureEngine:
    """
    Zentrale Engine zum Erzeugen aller ML-Features.

    Sprint 17.x
    - Instrument Features
    - Market Snapshot Features
    - Cross Asset Features
    - Macro Features
    - Konfigurierbare Feature-Pipeline
    """

    def __init__(self) -> None:
        self.builder = FeatureBuilder()

    def build(
        self,
        prices,
        indicators,
        *,
        market_snapshot: Any | None = None,
        cross_asset: Any | None = None,
        macro_features: Any | None = None,
        custom_features: Any | None = None,
    ):
        """
        Erzeugt den vollständigen Feature-Vektor.

        Alle zusätzlichen Feature-Gruppen sind optional,
        wodurch bestehender Code unverändert weiterläuft.
        """

        features = self.builder.build(
            prices=prices,
            indicators=indicators,
        )

        features = self._merge_optional(
            features,
            "merge_market_snapshot",
            market_snapshot,
        )

        features = self._merge_optional(
            features,
            "merge_cross_asset",
            cross_asset,
        )

        features = self._merge_optional(
            features,
            "merge_macro_features",
            macro_features,
        )

        features = self._merge_optional(
            features,
            "merge_custom_features",
            custom_features,
        )

        return features

    def _merge_optional(
        self,
        features,
        method_name: str,
        payload,
    ):
        if payload is None:
            return features

        method = getattr(
            self.builder,
            method_name,
            None,
        )

        if callable(method):
            return method(
                features,
                payload,
            )

        return features