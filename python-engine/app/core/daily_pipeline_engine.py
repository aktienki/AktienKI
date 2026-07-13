from __future__ import annotations

import logging
from dataclasses import asdict, dataclass
from datetime import datetime, timezone

from app.core.feature_store_engine import FeatureStoreEngine
from app.core.indicator_engine import IndicatorEngine
from app.core.market_importer import MarketImporter
from app.core.prediction_validation_engine import (
    PredictionValidationEngine,
)
from app.core.strategy_prediction_engine import (
    StrategyPredictionEngine,
)
from app.providers.yahoo_provider import YahooProvider
from app.repositories.instrument_repository import (
    InstrumentRepository,
)

logger = logging.getLogger(__name__)


@dataclass(slots=True)
class PipelineStepResult:
    name: str
    status: str
    result: dict | None = None
    error: str | None = None

    def to_dict(self) -> dict:
        return asdict(self)


class DailyPipelineEngine:
    def __init__(
        self,
        session_factory,
        *,
        yahoo_timeout_seconds: int,
        batch_size: int,
    ) -> None:
        self.session_factory = session_factory
        self.yahoo_timeout_seconds = yahoo_timeout_seconds
        self.batch_size = batch_size

    def run(
        self,
        *,
        symbol: str,
        strategy_code: str,
        algorithm: str = "xgboost",
        interval: str = "1d",
        validation_horizon_days: int = 5,
        continue_on_error: bool = False,
    ) -> dict:
        started_at = datetime.now(timezone.utc)

        instrument_id = self._resolve_instrument_id(symbol)

        steps: list[PipelineStepResult] = []

        step_definitions = [
            (
                "import_market",
                lambda: MarketImporter(
                    self.session_factory,
                    YahooProvider(self.yahoo_timeout_seconds),
                    batch_size=self.batch_size,
                ).run(
                    interval=interval,
                    period="10y",
                    symbol=symbol,
                    full=False,
                ),
            ),
            (
                "calculate_indicators",
                lambda: IndicatorEngine(
                    self.session_factory,
                    batch_size=self.batch_size,
                ).run(
                    interval=interval,
                    symbol=symbol,
                ),
            ),
            (
                "build_features",
                lambda: FeatureStoreEngine(
                    self.session_factory,
                    batch_size=self.batch_size,
                ).run(
                    interval=interval,
                    symbol=symbol,
                ),
            ),
            (
                "predict_strategy",
                lambda: StrategyPredictionEngine(
                    self.session_factory
                ).predict(
                    strategy_code=strategy_code,
                    instrument_id=instrument_id,
                    algorithm=algorithm,
                ),
            ),
            (
                "validate_predictions",
                lambda: PredictionValidationEngine(
                    self.session_factory
                ).run(
                    horizon_days=validation_horizon_days,
                ),
            ),
        ]

        for name, operation in step_definitions:
            try:
                result = operation()
                steps.append(
                    PipelineStepResult(
                        name=name,
                        status="completed",
                        result=result,
                    )
                )
                logger.info(
                    "Daily Pipeline Schritt '%s' abgeschlossen.",
                    name,
                )
            except Exception as exception:
                logger.exception(
                    "Daily Pipeline Schritt '%s' fehlgeschlagen.",
                    name,
                )
                steps.append(
                    PipelineStepResult(
                        name=name,
                        status="failed",
                        error=str(exception),
                    )
                )

                if not continue_on_error:
                    break

        finished_at = datetime.now(timezone.utc)
        failed_steps = [
            step for step in steps if step.status == "failed"
        ]

        return {
            "symbol": symbol,
            "instrument_id": instrument_id,
            "strategy_code": strategy_code,
            "algorithm": algorithm,
            "interval": interval,
            "status": (
                "completed"
                if not failed_steps
                else "failed"
            ),
            "started_at": started_at.isoformat(),
            "finished_at": finished_at.isoformat(),
            "duration_seconds": (
                finished_at - started_at
            ).total_seconds(),
            "steps": [step.to_dict() for step in steps],
        }

    def _resolve_instrument_id(self, symbol: str) -> int:
        with self.session_factory() as session:
            instruments = InstrumentRepository(session).active(
                symbol=symbol,
                limit=1,
            )

        if not instruments:
            raise RuntimeError(
                f"Instrument '{symbol}' wurde nicht gefunden."
            )

        return int(instruments[0].id)
