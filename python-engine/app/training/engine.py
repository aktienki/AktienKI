from __future__ import annotations

from app.training.prediction_engine import PredictionEngine
from app.training.training_runner import TrainingRunner


class AKIEngine:

    """
    Central AKI Engine

    Entry Point der kompletten KI.

    AKI-PULSE

    AKI-HORIZON

    AKI-CLIMATE

    AKI-NEXUS

    laufen ausschließlich über diese Klasse.
    """

    def __init__(self):

        self.training = TrainingRunner()

        self.prediction = PredictionEngine()

    # ---------------------------------------------------------

    #
    # Training
    #

    def train_short_term(self):

        return self.training.pulse()

    # ---------------------------------------------------------

    def train_long_term(self):

        return self.training.horizon()

    # ---------------------------------------------------------

    def train_market(self):

        return self.training.climate()

    # ---------------------------------------------------------

    #
    # Prediction
    #

    def predict(self):

        return self.prediction.predict()

    # ---------------------------------------------------------

    def predict_short_term(self):

        return self.prediction.short_term()

    # ---------------------------------------------------------

    def predict_long_term(self):

        return self.prediction.long_term()

    # ---------------------------------------------------------

    def predict_market(self):

        return self.prediction.market()

    # ---------------------------------------------------------

    def consensus(self):

        return self.prediction.consensus()


#
# Singleton
#

aki = AKIEngine()