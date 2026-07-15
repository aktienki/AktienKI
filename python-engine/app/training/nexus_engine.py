from __future__ import annotations

from dataclasses import asdict, dataclass
from typing import Any


@dataclass(slots=True, frozen=True)
class NexusInput:
    alias: str
    direction: str
    score: float
    confidence: float
    quality_weight: float = 1.0


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

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class NexusEngine:
    """
    AKI-NEXUS

    Kombiniert:
    - AKI-PULSE
    - AKI-HORIZON
    - AKI-CLIMATE

    Die Gewichtung kann dynamisch über quality_weight angepasst werden.
    """

    DEFAULT_WEIGHTS = {
        "AKI-PULSE": 0.25,
        "AKI-HORIZON": 0.55,
        "AKI-CLIMATE": 0.20,
    }

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
        weights: dict[str, float] | None = None,
    ) -> None:
        self.weights = {
            **self.DEFAULT_WEIGHTS,
            **(weights or {}),
        }

        self._validate_weights(self.weights)

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

        resolved_weights = self._resolved_weights(inputs)

        directional_score = 0.0
        confidence_score = 0.0
        absolute_score = 0.0

        components: dict[str, dict[str, float | str]] = {}

        for alias, item in inputs.items():
            direction_value = self._direction_value(
                item.direction
            )

            weight = resolved_weights[alias]

            normalized_score = self._normalize_score(
                item.score
            )

            normalized_confidence = self._normalize_confidence(
                item.confidence
            )

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
                "score": round(normalized_score * 100.0, 4),
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
            }

        consensus_score = round(
            directional_score * 100.0,
            4,
        )

        consensus_confidence = round(
            confidence_score,
            4,
        )

        direction = self._consensus_direction(
            consensus_score
        )

        status = self._consensus_status(
            pulse=pulse,
            horizon=horizon,
            climate=climate,
            consensus_score=consensus_score,
        )

        interpretation = self._interpretation(
            status=status,
            direction=direction,
            pulse=pulse,
            horizon=horizon,
            climate=climate,
        )

        return NexusResult(
            alias="AKI-NEXUS",
            direction=direction,
            score=round(
                absolute_score * 100.0,
                4,
            ),
            confidence=consensus_confidence,
            status=status,
            interpretation=interpretation,
            components=components,
            weights={
                alias: round(weight, 4)
                for alias, weight in resolved_weights.items()
            },
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

    def _resolved_weights(
        self,
        inputs: dict[str, NexusInput],
    ) -> dict[str, float]:
        weighted = {
            alias: (
                self.weights[alias]
                * max(0.0, item.quality_weight)
            )
            for alias, item in inputs.items()
        }

        total = sum(weighted.values())

        if total <= 0:
            raise ValueError(
                "Die effektiven AKI-NEXUS-Gewichte ergeben 0."
            )

        return {
            alias: value / total
            for alias, value in weighted.items()
        }

    @classmethod
    def _input_from_dict(
        cls,
        alias: str,
        payload: dict[str, Any],
    ) -> NexusInput:
        return NexusInput(
            alias=alias,
            direction=str(
                payload.get(
                    "direction",
                    "neutral",
                )
            ),
            score=float(
                payload.get(
                    "score",
                    payload.get(
                        "ensemble_score",
                        0.0,
                    ),
                )
            ),
            confidence=float(
                payload.get(
                    "confidence",
                    payload.get(
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
            .replace(" ", "_")
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
            value = value / 100.0

        return max(
            0.0,
            min(1.0, value),
        )

    @staticmethod
    def _normalize_confidence(
        confidence: float,
    ) -> float:
        value = float(confidence)

        if value > 1.0:
            value = value / 100.0

        return max(
            0.0,
            min(1.0, value),
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
        horizon_direction = cls._direction_value(
            horizon.direction
        )
        climate_direction = cls._direction_value(
            climate.direction
        )

        directions = [
            pulse_direction,
            horizon_direction,
            climate_direction,
        ]

        if all(value > 0 for value in directions):
            return "strong_agreement"

        if all(value < 0 for value in directions):
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
        pulse: NexusInput,
        horizon: NexusInput,
        climate: NexusInput,
    ) -> str:
        if status == "strong_agreement":
            if direction == "long":
                return (
                    "Kurzfristige, langfristige und Markt-KI "
                    "bestätigen gemeinsam ein bullisches Szenario."
                )

            if direction == "short":
                return (
                    "Kurzfristige, langfristige und Markt-KI "
                    "bestätigen gemeinsam ein bärisches Szenario."
                )

        if status == "short_term_pullback":
            return (
                "Der langfristige Trend bleibt positiv, "
                "während AKI-PULSE kurzfristig eine Korrektur erwartet."
            )

        if status == "short_term_recovery":
            return (
                "Der langfristige Trend bleibt negativ, "
                "während AKI-PULSE kurzfristig eine Erholung erkennt."
            )

        if status == "agreement":
            return (
                "Die Mehrheit der AKI-Systeme bestätigt "
                f"ein {direction.upper()}-Szenario."
            )

        if status == "mixed":
            return (
                "Die AKI-Systeme liefern derzeit kein klares "
                "gemeinsames Signal."
            )

        return (
            "Kurzfristige, langfristige und Markt-KI "
            "widersprechen sich deutlich."
        )

    @staticmethod
    def _validate_weights(
        weights: dict[str, float],
    ) -> None:
        required = {
            "AKI-PULSE",
            "AKI-HORIZON",
            "AKI-CLIMATE",
        }

        missing = sorted(
            required.difference(
                weights.keys()
            )
        )

        if missing:
            raise ValueError(
                "Fehlende AKI-NEXUS-Gewichte: "
                f"{missing}"
            )

        if any(
            float(value) < 0
            for value in weights.values()
        ):
            raise ValueError(
                "AKI-NEXUS-Gewichte dürfen nicht negativ sein."
            )


nexus_engine = NexusEngine()
