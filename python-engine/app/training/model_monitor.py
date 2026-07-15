from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime


@dataclass(slots=True)
class ModelHealth:

    alias: str

    algorithm: str

    version: str

    trained_at: datetime

    score: float

    direction_accuracy: float

    rmse: float

    r2: float

    drift_score: float

    feature_count: int

    quality: str

    status: str


class ModelMonitor:

    """
    Interner Monitor aller AKI Modelle.

    Wird später auch vom Dashboard verwendet.
    """

    def evaluate(

        self,

        alias,

        algorithm,

        version,

        metrics,

        feature_count,

        trained_at,

    ):

        drift = self.calculate_drift(

            metrics,

        )

        quality = self.calculate_quality(

            metrics,

        )

        status = (

            "ONLINE"

            if quality in [

                "A",

                "A+",

                "B",

            ]

            else "WARNING"

        )

        return ModelHealth(

            alias=alias,

            algorithm=algorithm,

            version=version,

            trained_at=trained_at,

            score=metrics["ensemble_score"],

            direction_accuracy=metrics["direction_accuracy"],

            rmse=metrics["rmse"],

            r2=metrics["r2"],

            drift_score=drift,

            feature_count=feature_count,

            quality=quality,

            status=status,

        )

    # --------------------------------------------------------

    @staticmethod
    def calculate_drift(

        metrics,

    ):

        score = 0.0

        score += abs(metrics["r2"])

        score += metrics["normalized_rmse"]

        return round(

            score,

            4,

        )

    # --------------------------------------------------------

    @staticmethod
    def calculate_quality(

        metrics,

    ):

        score = metrics["ensemble_score"]

        if score >= 85:

            return "A+"

        if score >= 70:

            return "A"

        if score >= 55:

            return "B"

        if score >= 40:

            return "C"

        return "D"