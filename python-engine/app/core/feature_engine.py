from __future__ import annotations

from app.features.feature_builder import FeatureBuilder


class FeatureEngine:
    """
    Erzeugt Instrumenten-Features und ergänzt später
    automatisch Market-Features.

    Sprint 17:
    - Instrument Features
    - Vorbereitung für Market Snapshot
    """

    def __init__(self):
        self.builder = FeatureBuilder()

    def build(
        self,
        prices,
        indicators,
        market_snapshot=None,
    ):
        """
        market_snapshot ist optional.

        Dadurch bleibt bestehender Code vollständig kompatibel.
        """

        features = self.builder.build(
            prices,
            indicators,
        )

        if market_snapshot is None:
            return features

        if hasattr(
            self.builder,
            "merge_market_snapshot",
        ):
            return self.builder.merge_market_snapshot(
                features,
                market_snapshot,
            )

        return features