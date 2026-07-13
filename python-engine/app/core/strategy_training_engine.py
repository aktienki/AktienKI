from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

from app.repositories.cross_asset_repository import CrossAssetRepository
from app.repositories.raw_price_repository import RawPriceRepository
from app.repositories.strategy_profile_repository import (
    StrategyProfileRepository,
)
from app.repositories.strategy_training_repository import (
    StrategyTrainingRepository,
)
from app.strategies.dynamic_feature_builder import DynamicFeatureBuilder
from app.training.evaluator import RegressionEvaluator
from app.training.factory import ModelFactory


class StrategyTrainingEngine:
    FEATURE_VERSION = "dynamic-1.2.0"

    def __init__(
        self,
        session_factory,
        *,
        storage_path: Path,
    ):
        self.session_factory = session_factory
        self.storage_path = storage_path
        self.storage_path.mkdir(parents=True, exist_ok=True)
        self.builder = DynamicFeatureBuilder()

    def train(
        self,
        *,
        strategy_code: str,
        instrument_id: int,
        algorithm: str = "xgboost",
        parameters: dict | None = None,
        configuration_override: dict | None = None,
    ) -> dict:
        with self.session_factory() as session:
            strategy = StrategyProfileRepository(
                session
            ).get_by_code(strategy_code)

            if strategy is None:
                raise RuntimeError(
                    f"Strategy Profile '{strategy_code}' wurde nicht gefunden."
                )

            if (
                strategy.allowed_algorithms
                and algorithm not in strategy.allowed_algorithms
            ):
                raise ValueError(
                    f"Algorithmus '{algorithm}' ist für "
                    f"'{strategy_code}' nicht erlaubt."
                )

            base_frame = RawPriceRepository(session).load(
                instrument_id=instrument_id,
                interval=strategy.interval,
            )

            cross_asset_frames = CrossAssetRepository(
                session
            ).load_frames(
                strategy_profile_id=strategy.id,
                interval=strategy.interval,
            )

        if base_frame.empty:
            raise RuntimeError(
                "Keine Kursdaten für das Zielinstrument gefunden."
            )

        if configuration_override is not None:
            strategy.configuration.clear()
            strategy.configuration.update(
                configuration_override
            )

        dynamic_frame = self.builder.build(
            base_frame=base_frame,
            strategy=strategy,
            cross_asset_frames=cross_asset_frames,
        )

        target_name = (
            f"target_return_{strategy.target_horizon_days}d"
        )

        feature_names = [
            column
            for column in dynamic_frame.columns
            if column not in {
                "open",
                "high",
                "low",
                "adjusted_close",
                target_name,
            }
            and not column.startswith("target_")
        ]

        training_frame = dynamic_frame[
            [*feature_names, target_name]
        ].dropna().copy()

        training_frame = training_frame.rename(
            columns={target_name: "target"}
        )

        if len(training_frame) < 300:
            raise ValueError(
                f"Zu wenig vollständige Trainingsdaten: "
                f"{len(training_frame)} Zeilen."
            )

        adapter = ModelFactory.create(algorithm)

        resolved_configuration = {
            "strategy_profile_id": strategy.id,
            "strategy_code": strategy.code,
            "strategy_version": strategy.version,
            "target_horizon_days": strategy.target_horizon_days,
            "interval": strategy.interval,
            "history_years": strategy.history_years,
            "algorithm": algorithm,
            "configuration": strategy.configuration,
            "feature_names": feature_names,
            "cross_asset_aliases": sorted(
                cross_asset_frames.keys()
            ),
        }

        with self.session_factory() as session:
            repository = StrategyTrainingRepository(session)

            definition_id = repository.get_or_create_definition(
                strategy_profile_id=strategy.id,
                strategy_profile_version=strategy.version,
                algorithm=algorithm,
                target_name=target_name,
                interval=strategy.interval,
                feature_version=self.FEATURE_VERSION,
                default_parameters=parameters or {},
            )

            run_id, public_id = repository.start_run(
                strategy_profile_id=strategy.id,
                strategy_profile_version=strategy.version,
                model_definition_id=definition_id,
                instrument_id=instrument_id,
                feature_version=self.FEATURE_VERSION,
                target_name=target_name,
                parameters=parameters or {},
                resolved_configuration=resolved_configuration,
            )
            session.commit()

        try:
            train_end = int(len(training_frame) * 0.80)
            validation_end = int(len(training_frame) * 0.90)

            train = training_frame.iloc[:train_end]
            validation = training_frame.iloc[
                train_end:validation_end
            ]
            test = training_frame.iloc[validation_end:]

            training_result = adapter.train(
                x_train=train[feature_names],
                y_train=train["target"],
                x_validation=validation[feature_names],
                y_validation=validation["target"],
                parameters=parameters,
            )

            test_prediction = adapter.predict(
                training_result.model,
                test[feature_names],
            )

            test_metrics = RegressionEvaluator.evaluate(
                test["target"],
                test_prediction,
            )

            metrics = {
                "validation": training_result.metrics,
                "test": test_metrics,
                "feature_importance": (
                    training_result.feature_importance
                ),
            }

            version = datetime.now(timezone.utc).strftime(
                "%Y%m%dT%H%M%SZ"
            )

            artifact_name = (
                f"{strategy.code}_{algorithm}_"
                f"instrument_{instrument_id}_{version}.joblib"
            )

            artifact_path = self.storage_path / artifact_name
            adapter.save(
                training_result.model,
                artifact_path,
            )

            metadata = {
                "strategy": resolved_configuration,
                "parameters": training_result.parameters,
                "metrics": metrics,
            }

            artifact_path.with_suffix(".json").write_text(
                json.dumps(metadata, indent=2),
                encoding="utf-8",
            )

            checksum = hashlib.sha256(
                artifact_path.read_bytes()
            ).hexdigest()

            with self.session_factory() as session:
                repository = StrategyTrainingRepository(session)

                trained_model_id = repository.save_model(
                    model_definition_id=definition_id,
                    instrument_id=instrument_id,
                    version=version,
                    artifact_path=str(artifact_path),
                    checksum=checksum,
                    training_period_start=training_frame.index[0],
                    training_period_end=training_frame.index[-1],
                    training_rows=len(train),
                    validation_rows=len(validation),
                    test_rows=len(test),
                    parameters=training_result.parameters,
                    metrics=metrics,
                    feature_names=feature_names,
                    metadata=metadata,
                )

                repository.complete_run(
                    run_id=run_id,
                    trained_model_id=trained_model_id,
                    metrics=metrics,
                )
                session.commit()

            return {
                "training_run_id": public_id,
                "trained_model_id": trained_model_id,
                "strategy_code": strategy.code,
                "strategy_version": strategy.version,
                "algorithm": algorithm,
                "target_name": target_name,
                "feature_count": len(feature_names),
                "cross_asset_aliases": sorted(
                    cross_asset_frames.keys()
                ),
                "feature_names": feature_names,
                "rows": {
                    "training": len(train),
                    "validation": len(validation),
                    "test": len(test),
                },
                "metrics": metrics,
                "artifact_path": str(artifact_path),
            }

        except Exception as exception:
            with self.session_factory() as session:
                repository = StrategyTrainingRepository(session)
                repository.fail_run(
                    run_id=run_id,
                    error_message=str(exception),
                )
                session.commit()
            raise
