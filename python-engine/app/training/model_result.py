from __future__ import annotations

from dataclasses import dataclass


@dataclass(slots=True)
class ModelResult:

    alias: str

    algorithm: str

    score: float

    confidence: float

    direction: str

    prediction: float

    model: object