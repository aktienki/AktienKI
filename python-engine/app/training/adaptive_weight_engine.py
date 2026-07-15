from __future__ import annotations

from dataclasses import dataclass
from typing import Any


@dataclass(slots=True, frozen=True)
class AdaptiveWeightResult:
    weights: dict[str, float]
    reasons: list[str]


class AdaptiveWeightEngine:
    """
    Berechnet dynamische Gewichte für AKI-NEXUS.

    Berücksichtigt:
    - Ensemble-Score
    - Richtungsgenauigkeit
    - R²
    - normalisierten RMSE
    - optionale Qualitätsgewichte

    Die Gewichte werden immer auf 1.0 normalisiert.
    """

    DEFAULT_WEIGHTS = {
        "AKI-PULSE": 0.25,
        "AKI-HORIZON": 0.55,
        "AKI-CLIMATE": 0.20,
    }

    MIN_WEIGHT = 0.05

    @classmethod
    def calculate(
        cls,
        *,
        pulse: dict[str, Any],
        horizon: dict[str, Any],
        climate: dict[str, Any],
    ) -> AdaptiveWeightResult:
        weights = cls.DEFAULT_WEIGHTS.copy()
        reasons: list[str] = []

        pulse_quality = cls._quality_score(pulse)
        horizon_quality = cls._quality_score(horizon)
        climate_quality = cls._quality_score(climate)

        if pulse_quality > horizon_quality + 0.05:
            shift = min(
                0.10,
                (pulse_quality - horizon_quality) * 0.20,
            )

            weights["AKI-PULSE"] += shift
            weights["AKI-HORIZON"] -= shift

            reasons.append(
                "AKI-PULSE wurde höher gewichtet, "
                "weil seine aktuelle Modellqualität über "
                "AKI-HORIZON liegt."
            )

        elif horizon_quality > pulse_quality + 0.05:
            shift = min(
                0.10,
                (horizon_quality - pulse_quality) * 0.20,
            )

            weights["AKI-HORIZON"] += shift
            weights["AKI-PULSE"] -= shift

            reasons.append(
                "AKI-HORIZON wurde höher gewichtet, "
                "weil seine aktuelle Modellqualität über "
                "AKI-PULSE liegt."
            )

        if climate_quality >= 0.80:
            climate_shift = 0.05

            weights["AKI-CLIMATE"] += climate_shift
            weights["AKI-PULSE"] -= climate_shift / 2
            weights["AKI-HORIZON"] -= climate_shift / 2

            reasons.append(
                "AKI-CLIMATE wurde wegen hoher Marktmodellqualität "
                "stärker gewichtet."
            )

        elif climate_quality <= 0.35:
            climate_shift = 0.05

            weights["AKI-CLIMATE"] -= climate_shift
            weights["AKI-PULSE"] += climate_shift * 0.40
            weights["AKI-HORIZON"] += climate_shift * 0.60

            reasons.append(
                "AKI-CLIMATE wurde wegen niedriger Modellqualität "
                "schwächer gewichtet."
            )

        weights = cls._apply_quality_weight(
            weights=weights,
            alias="AKI-PULSE",
            payload=pulse,
        )

        weights = cls._apply_quality_weight(
            weights=weights,
            alias="AKI-HORIZON",
            payload=horizon,
        )

        weights = cls._apply_quality_weight(
            weights=weights,
            alias="AKI-CLIMATE",
            payload=climate,
        )

        normalized = cls._normalize(weights)

        if not reasons:
            reasons.append(
                "Standardgewichtung wurde verwendet, "
                "weil keine deutlichen Qualitätsunterschiede vorlagen."
            )

        return AdaptiveWeightResult(
            weights=normalized,
            reasons=reasons,
        )

    @classmethod
    def _quality_score(
        cls,
        payload: dict[str, Any],
    ) -> float:
        ensemble_score = cls._normalize_percent(
            payload.get(
                "ensemble_score",
                payload.get("score", 0.0),
            )
        )

        direction_accuracy = cls._normalize_percent(
            payload.get(
                "direction_accuracy",
                payload.get("confidence", 0.0),
            )
        )

        r2 = cls._clip(
            float(
                payload.get(
                    "r2",
                    0.0,
                )
            ),
            minimum=0.0,
            maximum=1.0,
        )

        normalized_rmse = float(
            payload.get(
                "normalized_rmse",
                1.0,
            )
        )

        rmse_quality = (
            1.0
            / (
                1.0
                + max(
                    0.0,
                    normalized_rmse,
                )
            )
        )

        quality = (
            ensemble_score * 0.35
            + direction_accuracy * 0.30
            + r2 * 0.20
            + rmse_quality * 0.15
        )

        return cls._clip(
            quality,
            minimum=0.0,
            maximum=1.0,
        )

    @classmethod
    def _apply_quality_weight(
        cls,
        *,
        weights: dict[str, float],
        alias: str,
        payload: dict[str, Any],
    ) -> dict[str, float]:
        quality_weight = float(
            payload.get(
                "quality_weight",
                1.0,
            )
        )

        quality_weight = cls._clip(
            quality_weight,
            minimum=0.10,
            maximum=2.00,
        )

        adjusted = weights.copy()
        adjusted[alias] *= quality_weight

        return adjusted

    @classmethod
    def _normalize(
        cls,
        weights: dict[str, float],
    ) -> dict[str, float]:
        bounded = {
            alias: max(
                cls.MIN_WEIGHT,
                float(value),
            )
            for alias, value in weights.items()
        }

        total = sum(
            bounded.values()
        )

        if total <= 0:
            raise ValueError(
                "Die dynamischen AKI-NEXUS-Gewichte ergeben 0."
            )

        return {
            alias: round(
                value / total,
                6,
            )
            for alias, value in bounded.items()
        }

    @staticmethod
    def _normalize_percent(
        value: Any,
    ) -> float:
        number = float(
            value or 0.0
        )

        if number > 1.0:
            number /= 100.0

        return AdaptiveWeightEngine._clip(
            number,
            minimum=0.0,
            maximum=1.0,
        )

    @staticmethod
    def _clip(
        value: float,
        *,
        minimum: float,
        maximum: float,
    ) -> float:
        return max(
            minimum,
            min(
                maximum,
                float(value),
            ),
        )


adaptive_weight_engine = AdaptiveWeightEngine()
