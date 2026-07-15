from __future__ import annotations

from dataclasses import asdict, dataclass
from typing import Any

from app.training.adaptive_weight_engine import (
    AdaptiveWeightEngine,
)


@dataclass(slots=True, frozen=True)
class NexusInput:
    alias: str
    direction: str
    score: float
    confidence: float
    quality_weight: float = 1.0
    r2: float = 0.0
    normalized_rmse: float = 1.0
    direction_accuracy: float = 0.0
    ensemble_score: float = 0.0


@dataclass(slots=True, frozen=True)
class NexusResult:
    alias: str
    direction: str
    score: float
    confidence: float
    status: str
    interpretation: str
    components: dict[str, dict[str, float | str]]
    weights: dict[str, float]
    weight_reasons: list[str]

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class NexusEngine:
    """
    AKI-NEXUS

    Kombiniert:
    - AKI-PULSE
    - AKI-HORIZON
    - AKI-CLIMATE

    Die Gewichte werden dynamisch anhand der aktuellen
    Modellqualität berechnet.
    """

    DIRECTION_VALUES = {
        "long": 1.0,
        "bullish": 1.0,
        "buy": 1.0,
        "strong_buy": 1.0,
        "neutral": 0.0,
        "hold": 0.0,
        "mixed": 0.0,
        "conflict": 0.0,
        "short": -1.0,
        "bearish": -1.0,
        "sell": -1.0,
        "strong_sell": -1.0,
    }

    def __init__(
        self,
        *,
        adaptive_weight_engine: type[
            AdaptiveWeightEngine
        ] = AdaptiveWeightEngine,
    ) -> None:
        self.adaptive_weight_engine = (
            adaptive_weight_engine
        )

    def calculate(
        self,
        *,
        pulse: NexusInput,
        horizon: NexusInput,
        climate: NexusInput,
    ) -> NexusResult:
        inputs = {
            "AKI-PULSE": pulse,
            "AKI-HORIZON": horizon,
            "AKI-CLIMATE": climate,
        }

        adaptive_result = (
            self.adaptive_weight_engine.calculate(
                pulse=self._quality_payload(
                    pulse
                ),
                horizon=self._quality_payload(
                    horizon
                ),
                climate=self._quality_payload(
                    climate
                ),
            )
        )

        weights = adaptive_result.weights

        directional_score = 0.0
        confidence_score = 0.0
        absolute_score = 0.0

        components: dict[
            str,
            dict[str, float | str],
        ] = {}

        for alias, item in inputs.items():
            direction_value = (
                self._direction_value(
                    item.direction
                )
            )

            normalized_score = (
                self._normalize_score(
                    item.score
                )
            )

            normalized_confidence = (
                self._normalize_confidence(
                    item.confidence
                )
            )

            weight = weights[alias]

            directional_score += (
                direction_value
                * normalized_score
                * normalized_confidence
                * weight
            )

            confidence_score += (
                normalized_confidence
                * weight
            )

            absolute_score += (
                normalized_score
                * weight
            )

            components[alias] = {
                "direction": item.direction,
                "score": round(
                    normalized_score * 100.0,
                    4,
                ),
                "confidence": round(
                    normalized_confidence,
                    4,
                ),
                "quality_weight": round(
                    item.quality_weight,
                    4,
                ),
                "effective_weight": round(
                    weight,
                    4,
                ),
                "r2": round(
                    item.r2,
                    6,
                ),
                "normalized_rmse": round(
                    item.normalized_rmse,
                    6,
                ),
                "direction_accuracy": round(
                    item.direction_accuracy,
                    6,
                ),
                "ensemble_score": round(
                    item.ensemble_score,
                    6,
                ),
            }

        consensus_score = round(
            directional_score * 100.0,
            4,
        )

        consensus_confidence = round(
            confidence_score,
            4,
        )

        direction = (
            self._consensus_direction(
                consensus_score
            )
        )

        status = self._consensus_status(
            pulse=pulse,
            horizon=horizon,
            climate=climate,
            consensus_score=consensus_score,
        )

        interpretation = (
            self._interpretation(
                status=status,
                direction=direction,
            )
        )

        return NexusResult(
            alias="AKI-NEXUS",
            direction=direction,
            score=round(
                absolute_score * 100.0,
                4,
            ),
            confidence=(
                consensus_confidence
            ),
            status=status,
            interpretation=interpretation,
            components=components,
            weights={
                alias: round(
                    weight,
                    6,
                )
                for alias, weight in weights.items()
            },
            weight_reasons=(
                adaptive_result.reasons
            ),
        )

    def from_dicts(
        self,
        *,
        pulse: dict[str, Any],
        horizon: dict[str, Any],
        climate: dict[str, Any],
    ) -> NexusResult:
        return self.calculate(
            pulse=self._input_from_dict(
                "AKI-PULSE",
                pulse,
            ),
            horizon=self._input_from_dict(
                "AKI-HORIZON",
                horizon,
            ),
            climate=self._input_from_dict(
                "AKI-CLIMATE",
                climate,
            ),
        )

    @staticmethod
    def _quality_payload(
        item: NexusInput,
    ) -> dict[str, float]:
        return {
            "score": item.score,
            "confidence": item.confidence,
            "quality_weight": (
                item.quality_weight
            ),
            "r2": item.r2,
            "normalized_rmse": (
                item.normalized_rmse
            ),
            "direction_accuracy": (
                item.direction_accuracy
            ),
            "ensemble_score": (
                item.ensemble_score
            ),
        }

    @classmethod
    def _input_from_dict(
        cls,
        alias: str,
        payload: dict[str, Any],
    ) -> NexusInput:
        metrics = payload.get(
            "metrics",
            payload,
        )

        return NexusInput(
            alias=alias,
            direction=str(
                payload.get(
                    "direction",
                    metrics.get(
                        "direction",
                        "neutral",
                    ),
                )
            ),
            score=float(
                payload.get(
                    "score",
                    metrics.get(
                        "ensemble_score",
                        0.0,
                    ),
                )
            ),
            confidence=float(
                payload.get(
                    "confidence",
                    metrics.get(
                        "direction_accuracy",
                        0.0,
                    ),
                )
            ),
            quality_weight=float(
                payload.get(
                    "quality_weight",
                    1.0,
                )
            ),
            r2=float(
                metrics.get(
                    "r2",
                    0.0,
                )
            ),
            normalized_rmse=float(
                metrics.get(
                    "normalized_rmse",
                    1.0,
                )
            ),
            direction_accuracy=float(
                metrics.get(
                    "direction_accuracy",
                    0.0,
                )
            ),
            ensemble_score=float(
                metrics.get(
                    "ensemble_score",
                    payload.get(
                        "score",
                        0.0,
                    ),
                )
            ),
        )

    @classmethod
    def _direction_value(
        cls,
        direction: str,
    ) -> float:
        normalized = (
            direction
            .strip()
            .lower()
            .replace(
                " ",
                "_",
            )
        )

        return cls.DIRECTION_VALUES.get(
            normalized,
            0.0,
        )

    @staticmethod
    def _normalize_score(
        score: float,
    ) -> float:
        value = float(score)

        if value > 1.0:
            value /= 100.0

        return max(
            0.0,
            min(
                1.0,
                value,
            ),
        )

    @staticmethod
    def _normalize_confidence(
        confidence: float,
    ) -> float:
        value = float(confidence)

        if value > 1.0:
            value /= 100.0

        return max(
            0.0,
            min(
                1.0,
                value,
            ),
        )

    @staticmethod
    def _consensus_direction(
        consensus_score: float,
    ) -> str:
        if consensus_score >= 15:
            return "long"

        if consensus_score <= -15:
            return "short"

        return "neutral"

    @classmethod
    def _consensus_status(
        cls,
        *,
        pulse: NexusInput,
        horizon: NexusInput,
        climate: NexusInput,
        consensus_score: float,
    ) -> str:
        pulse_direction = cls._direction_value(
            pulse.direction
        )
        horizon_direction = (
            cls._direction_value(
                horizon.direction
            )
        )
        climate_direction = (
            cls._direction_value(
                climate.direction
            )
        )

        directions = [
            pulse_direction,
            horizon_direction,
            climate_direction,
        ]

        if all(
            value > 0
            for value in directions
        ):
            return "strong_agreement"

        if all(
            value < 0
            for value in directions
        ):
            return "strong_agreement"

        if (
            horizon_direction > 0
            and pulse_direction < 0
        ):
            return "short_term_pullback"

        if (
            horizon_direction < 0
            and pulse_direction > 0
        ):
            return "short_term_recovery"

        positive = sum(
            value > 0
            for value in directions
        )

        negative = sum(
            value < 0
            for value in directions
        )

        if positive >= 2 or negative >= 2:
            return "agreement"

        if abs(consensus_score) < 15:
            return "mixed"

        return "conflict"

    @staticmethod
    def _interpretation(
        *,
        status: str,
        direction: str,
    ) -> str:
        if status == "strong_agreement":
            if direction == "long":
                return (
                    "AKI-PULSE, AKI-HORIZON und "
                    "AKI-CLIMATE bestätigen gemeinsam "
                    "ein bullisches Szenario."
                )

            if direction == "short":
                return (
                    "AKI-PULSE, AKI-HORIZON und "
                    "AKI-CLIMATE bestätigen gemeinsam "
                    "ein bärisches Szenario."
                )

        if status == "short_term_pullback":
            return (
                "Der langfristige Trend bleibt positiv, "
                "während AKI-PULSE kurzfristig eine "
                "Korrektur erwartet."
            )

        if status == "short_term_recovery":
            return (
                "Der langfristige Trend bleibt negativ, "
                "während AKI-PULSE kurzfristig eine "
                "Erholung erkennt."
            )

        if status == "agreement":
            return (
                "Die Mehrheit der AKI-Systeme bestätigt "
                f"ein {direction.upper()}-Szenario."
            )

        if status == "mixed":
            return (
                "Die AKI-Systeme liefern derzeit kein "
                "klares gemeinsames Signal."
            )

        return (
            "AKI-PULSE, AKI-HORIZON und AKI-CLIMATE "
            "widersprechen sich deutlich."
        )


nexus_engine = NexusEngine()
