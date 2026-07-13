import logging
from pathlib import Path

from app.config.settings import Settings
from app.core.cross_asset_import_engine import CrossAssetImportEngine
from app.core.daily_pipeline_engine import DailyPipelineEngine
from app.core.feature_store_engine import FeatureStoreEngine
from app.core.indicator_engine import IndicatorEngine
from app.core.market_importer import MarketImporter
from app.core.prediction_engine import PredictionEngine
from app.core.prediction_validation_engine import PredictionValidationEngine
from app.core.strategy_experiment_engine import StrategyExperimentEngine
from app.core.strategy_prediction_engine import StrategyPredictionEngine
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
        return MarketImporter(
            self.session_factory,
            YahooProvider(self.settings.yahoo_timeout_seconds),
            batch_size=self.settings.import_batch_size,
        ).run(**kwargs)

    def import_cross_assets(self, **kwargs):
        return CrossAssetImportEngine(
            self.session_factory,
            yahoo_timeout_seconds=(
                self.settings.yahoo_timeout_seconds
            ),
            batch_size=self.settings.import_batch_size,
        ).run(**kwargs)

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

    def run_experiment(self, **kwargs):
        return StrategyExperimentEngine(
            self.session_factory,
            storage_path=Path("storage/models").resolve(),
        ).run(**kwargs)

    def predict(self, **kwargs):
        return PredictionEngine(
            self.session_factory
        ).predict(**kwargs)

    def predict_strategy(self, **kwargs):
        return StrategyPredictionEngine(
            self.session_factory
        ).predict(**kwargs)

    def validate_predictions(self, **kwargs):
        return PredictionValidationEngine(
            self.session_factory
        ).run(**kwargs)

    def daily_run(self, **kwargs):
        return DailyPipelineEngine(
            self.session_factory,
            yahoo_timeout_seconds=(
                self.settings.yahoo_timeout_seconds
            ),
            batch_size=self.settings.import_batch_size,
        ).run(**kwargs)
