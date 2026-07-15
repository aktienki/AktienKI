from __future__ import annotations

from dataclasses import dataclass

from app.training.model_result import (
    ModelResult,
)


@dataclass(slots=True)
class PredictionResult:

    pulse: ModelResult

    horizon: ModelResult

    climate: ModelResult

    consensus: object