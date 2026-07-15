from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime


@dataclass(slots=True)
class ModelHistoryEntry:

    alias: str

    version: str

    algorithm: str

    score: float

    direction_accuracy: float

    rmse: float

    r2: float

    feature_count: int

    trained_at: datetime

    champion: bool


class ModelHistory:

    """
    Speichert alle TrainingslÃ¤ufe.

    Daraus entstehen spÃ¤ter

    - ELO
    - Drift
    - Retraining
    - AKI-PRIME
    """

    def __init__(self):

        self.history = []

    # -----------------------------------------------------

    def add(

        self,

        entry: ModelHistoryEntry,

    ):

        self.history.append(entry)

    # -----------------------------------------------------

    def latest(

        self,

        alias,

    ):

        models = [

            x

            for x in self.history

            if x.alias == alias

        ]

        if len(models) == 0:

            return None

        return sorted(

            models,

            key=lambda x: x.trained_at,

            reverse=True,

        )[0]

    # -----------------------------------------------------

    def champion(

        self,

        alias,

    ):

        champions = [

            x

            for x in self.history

            if x.alias == alias

            and x.champion

        ]

        if len(champions) == 0:

            return None

        return sorted(

            champions,

            key=lambda x: x.score,

            reverse=True,

        )[0]