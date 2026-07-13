from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

import joblib

from app.repositories.feature_store_repository import FeatureStoreRepository
from app.repositories.model_repository import ModelRepository
from app.training.evaluator import RegressionEvaluator
from app.training.factory import ModelFactory


class TrainingEngine:
    def __init__(
        self,
        session_factory,
        *,
        storage_path: Path,
    ):
        self.session_factory = session_factory
        self.storage_path = storage_path
        self.storage_path.mkdir(parents=True, exist_ok=True)

    def train(
        self,
        *,
        instrument_id: int,
        algorithm: str = "xgboost",
        interval: str = "1d",
        feature_version: str = "1.0.0",
        target_name: str = "target_return_5d",
        parameters: dict | None = None,
    ) -> dict:
        adapter = ModelFactory.create(algorithm)
        feature_names = FeatureStoreRepository.FEATURE_COLUMNS

        with self.session_factory() as session:
            feature_repository = FeatureStoreRepository(session)
            model_repository = ModelRepository(session)

            frame = feature_repository.load_training_data(
                instrument_id=instrument_id,
                interval=interval,
                feature_version=feature_version,
                target_name=target_name,
            )

            if len(frame) < 300:
                raise ValueError(
                    f"Zu wenig Trainingsdaten: {len(frame)} Zeilen. "
                    "Mindestens 300 vollständige Zeilen werden benötigt."
                )

            model_code = (
                f"{algorithm}_{target_name}_{feature_version}"
            )

            definition_id = model_repository.get_or_create_definition(
                code=model_code,
                name=(
                    f"{algorithm.upper()} "
                    f"{target_name} "
                    f"Feature {feature_version}"
                ),
                algorithm=algorithm,
                target_name=target_name,
                interval=interval,
                feature_version=feature_version,
                default_parameters=parameters or {},
            )

            run_id, public_id = model_repository.start_training_run(
                model_definition_id=definition_id,
                instrument_id=instrument_id,
                feature_version=feature_version,
                target_name=target_name,
                parameters=parameters or {},
            )
            session.commit()

        try:
            train_end = int(len(frame) * 0.80)
            validation_end = int(len(frame) * 0.90)

            train = frame.iloc[:train_end]
            validation = frame.iloc[train_end:validation_end]
            test = frame.iloc[validation_end:]

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
                f"{algorithm}_instrument_{instrument_id}_"
                f"{target_name}_{version}.joblib"
            )

            artifact_path = self.storage_path / artifact_name
            adapter.save(
                training_result.model,
                artifact_path,
            )

            metadata_path = artifact_path.with_suffix(".json")
            metadata_path.write_text(
                json.dumps(
                    {
                        "algorithm": algorithm,
                        "version": version,
                        "instrument_id": instrument_id,
                        "target_name": target_name,
                        "feature_version": feature_version,
                        "feature_names": feature_names,
                        "parameters": training_result.parameters,
                        "metrics": metrics,
                    },
                    indent=2,
                ),
                encoding="utf-8",
            )

            checksum = hashlib.sha256(
                artifact_path.read_bytes()
            ).hexdigest()

            with self.session_factory() as session:
                repository = ModelRepository(session)

                trained_model_id = repository.save_trained_model(
                    model_definition_id=definition_id,
                    instrument_id=instrument_id,
                    version=version,
                    artifact_path=str(artifact_path),
                    checksum=checksum,
                    training_period_start=frame.iloc[0]["bar_time"],
                    training_period_end=frame.iloc[-1]["bar_time"],
                    training_rows=len(train),
                    validation_rows=len(validation),
                    test_rows=len(test),
                    parameters=training_result.parameters,
                    metrics=metrics,
                    feature_names=feature_names,
                )

                repository.complete_training_run(
                    run_id=run_id,
                    trained_model_id=trained_model_id,
                    metrics=metrics,
                )
                session.commit()

            return {
                "training_run_id": public_id,
                "trained_model_id": trained_model_id,
                "algorithm": algorithm,
                "artifact_path": str(artifact_path),
                "metrics": metrics,
                "rows": {
                    "training": len(train),
                    "validation": len(validation),
                    "test": len(test),
                },
            }

        except Exception as exception:
            with self.session_factory() as session:
                repository = ModelRepository(session)
                repository.fail_training_run(
                    run_id=run_id,
                    error_message=str(exception),
                )
                session.commit()
            raise
