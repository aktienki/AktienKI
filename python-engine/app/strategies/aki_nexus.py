from __future__ import annotations

from dataclasses import dataclass


@dataclass(slots=True)
class ConsensusResult:

    direction: str

    score: float

    confidence: float

    status: str

    interpretation: str


class AKINexusStrategy:
    """
    Meta AI

    Kombiniert:

        AKI-PULSE
        AKI-HORIZON
        AKI-CLIMATE
    """

    def calculate(
        self,
        pulse,
        horizon,
        climate,
    ) -> ConsensusResult:

        weights = {

            "pulse": 0.25,

            "horizon": 0.55,

            "climate": 0.20,

        }

        score = (

            pulse.ai_score * weights["pulse"]

            + horizon.ai_score * weights["horizon"]

            + climate.ai_score * weights["climate"]

        )

        confidence = (

            pulse.confidence * weights["pulse"]

            + horizon.confidence * weights["horizon"]

            + climate.confidence * weights["climate"]

        )

        directions = [

            pulse.direction,

            horizon.direction,

            climate.direction,

        ]

        long_count = directions.count("long")

        short_count = directions.count("short")

        if long_count == 3:

            status = "strong_agreement"

            direction = "long"

            interpretation = (

                "Alle KI-Systeme bestätigen "

                "ein bullisches Szenario."

            )

        elif short_count == 3:

            status = "strong_agreement"

            direction = "short"

            interpretation = (

                "Alle KI-Systeme bestätigen "

                "ein bärisches Szenario."

            )

        elif long_count == 2:

            status = "agreement"

            direction = "long"

            interpretation = (

                "Mehrheit der KI-Systeme "

                "spricht für LONG."

            )

        elif short_count == 2:

            status = "agreement"

            direction = "short"

            interpretation = (

                "Mehrheit der KI-Systeme "

                "spricht für SHORT."

            )

        else:

            status = "conflict"

            direction = "neutral"

            interpretation = (

                "Kurz- und langfristige "

                "Modelle widersprechen sich."

            )

        return ConsensusResult(

            direction=direction,

            score=round(score, 2),

            confidence=round(confidence, 4),

            status=status,

            interpretation=interpretation,

        )