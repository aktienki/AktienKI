import logging
from pathlib import Path

from app.config.settings import Settings
from app.core.feature_store_engine import FeatureStoreEngine
from app.core.indicator_engine import IndicatorEngine
from app.core.market_importer import MarketImporter
from app.core.prediction_engine import PredictionEngine
from app.core.strategy_training_engine import StrategyTrainingEngine
from app.core.training_engine import TrainingEngine
from app.database.session import build_engine, build_session_factory
from app.providers.yahoo_provider import YahooProvider


class Engine:
    def __init__(self, settings=None):
        self.settings = settings or Settings.load()

        logging.basicConfig(
            level=getattr(
                logging,
                self.settings.log_level,
                logging.INFO,
            ),
            format="%(asctime)s | %(levelname)s | %(message)s",
        )

        self.session_factory = build_session_factory(
            build_engine(self.settings)
        )

    def import_market(self, **kwargs):
        engine = MarketImporter(
            self.session_factory,
            YahooProvider(self.settings.yahoo_timeout_seconds),
            batch_size=self.settings.import_batch_size,
        )
        return engine.run(**kwargs)

    def calculate_indicators(self, **kwargs):
        return IndicatorEngine(
            self.session_factory,
            batch_size=self.settings.import_batch_size,
        ).run(**kwargs)

    def build_features(self, **kwargs):
        return FeatureStoreEngine(
            self.session_factory,
            batch_size=self.settings.import_batch_size,
        ).run(**kwargs)

    def train(self, **kwargs):
        return TrainingEngine(
            self.session_factory,
            storage_path=Path("storage/models").resolve(),
        ).train(**kwargs)

    def train_strategy(self, **kwargs):
        return StrategyTrainingEngine(
            self.session_factory,
            storage_path=Path("storage/models").resolve(),
        ).train(**kwargs)

    def predict(self, **kwargs):
        return PredictionEngine(
            self.session_factory
        ).predict(**kwargs)
