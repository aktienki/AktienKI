from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

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
    ) -> None:
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
        requested_algorithm = algorithm.lower().strip()
        candidate_algorithms = self._candidate_algorithms(
            requested_algorithm
        )
        feature_names = list(
            FeatureStoreRepository.FEATURE_COLUMNS
        )

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
                f"{requested_algorithm}_{target_name}_{feature_version}"
            )

            definition_id = model_repository.get_or_create_definition(
                code=model_code,
                name=(
                    f"{requested_algorithm.upper()} "
                    f"{target_name} "
                    f"Feature {feature_version}"
                ),
                algorithm=requested_algorithm,
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
                parameters={
                    "requested_algorithm": requested_algorithm,
                    "candidate_algorithms": candidate_algorithms,
                    "candidate_parameters": parameters or {},
                },
            )
            session.commit()

        try:
            train, validation, test = self._split_frame(frame)

            candidates: dict[str, dict[str, Any]] = {}
            candidate_errors: dict[str, str] = {}

            for candidate_algorithm in candidate_algorithms:
                try:
                    adapter = ModelFactory.create(
                        candidate_algorithm
                    )
                    candidate_parameters = (
                        self._parameters_for_algorithm(
                            parameters,
                            candidate_algorithm,
                            requested_algorithm,
                        )
                    )

                    training_result = adapter.train(
                        x_train=train[feature_names],
                        y_train=train["target"],
                        x_validation=validation[feature_names],
                        y_validation=validation["target"],
                        parameters=candidate_parameters,
                    )

                    test_prediction = adapter.predict(
                        training_result.model,
                        test[feature_names],
                    )

                    test_metrics = RegressionEvaluator.evaluate(
                        test["target"],
                        test_prediction,
                    )

                    candidates[candidate_algorithm] = {
                        "adapter": adapter,
                        "training_result": training_result,
                        "validation_metrics": (
                            training_result.metrics
                        ),
                        "test_metrics": test_metrics,
                        "ensemble_score": float(
                            test_metrics.get(
                                "ensemble_score",
                                float("-inf"),
                            )
                        ),
                    }

                except Exception as exception:
                    candidate_errors[candidate_algorithm] = str(
                        exception
                    )

            if not candidates:
                details = "; ".join(
                    f"{name}: {message}"
                    for name, message in candidate_errors.items()
                )
                raise RuntimeError(
                    "Kein Kandidatenmodell konnte trainiert werden. "
                    + details
                )

            winner_algorithm = max(
                candidates,
                key=lambda name: candidates[name][
                    "ensemble_score"
                ],
            )
            winner = candidates[winner_algorithm]
            winner_adapter = winner["adapter"]
            winner_result = winner["training_result"]

            candidate_metrics = {
                name: {
                    "validation": candidate[
                        "validation_metrics"
                    ],
                    "test": candidate["test_metrics"],
                    "ensemble_score": candidate[
                        "ensemble_score"
                    ],
                }
                for name, candidate in candidates.items()
            }

            metrics = {
                "validation": winner["validation_metrics"],
                "test": winner["test_metrics"],
                "feature_importance": (
                    winner_result.feature_importance
                ),
                "adaptive_ensemble": {
                    "enabled": requested_algorithm == "ensemble",
                    "requested_algorithm": requested_algorithm,
                    "winner_algorithm": winner_algorithm,
                    "candidate_algorithms": candidate_algorithms,
                    "candidates": candidate_metrics,
                    "errors": candidate_errors,
                },
            }

            version = datetime.now(timezone.utc).strftime(
                "%Y%m%dT%H%M%SZ"
            )

            artifact_name = (
                f"{requested_algorithm}_instrument_{instrument_id}_"
                f"{target_name}_{version}.joblib"
            )
            artifact_path = self.storage_path / artifact_name

            winner_adapter.save(
                winner_result.model,
                artifact_path,
            )

            metadata_path = artifact_path.with_suffix(".json")
            metadata_path.write_text(
                json.dumps(
                    {
                        "algorithm": requested_algorithm,
                        "winner_algorithm": winner_algorithm,
                        "candidate_algorithms": candidate_algorithms,
                        "version": version,
                        "instrument_id": instrument_id,
                        "target_name": target_name,
                        "feature_version": feature_version,
                        "feature_names": feature_names,
                        "parameters": winner_result.parameters,
                        "metrics": metrics,
                    },
                    indent=2,
                    default=str,
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
                    training_period_start=frame.iloc[0][
                        "bar_time"
                    ],
                    training_period_end=frame.iloc[-1][
                        "bar_time"
                    ],
                    training_rows=len(train),
                    validation_rows=len(validation),
                    test_rows=len(test),
                    parameters={
                        **winner_result.parameters,
                        "requested_algorithm": requested_algorithm,
                        "winner_algorithm": winner_algorithm,
                    },
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
                "algorithm": requested_algorithm,
                "winner_algorithm": winner_algorithm,
                "candidate_algorithms": candidate_algorithms,
                "candidate_errors": candidate_errors,
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

    @staticmethod
    def _candidate_algorithms(
        requested_algorithm: str,
    ) -> list[str]:
        if requested_algorithm == "ensemble":
            algorithms = ModelFactory.available_models()
            if not algorithms:
                raise RuntimeError(
                    "In der ModelFactory sind keine Modelle registriert."
                )
            return algorithms

        if not ModelFactory.is_supported(requested_algorithm):
            supported = ", ".join(
                ModelFactory.available_models()
            )
            raise ValueError(
                f"Unbekanntes Modell '{requested_algorithm}'. "
                f"Unterstützt: {supported}, ensemble"
            )

        return [requested_algorithm]

    @staticmethod
    def _parameters_for_algorithm(
        parameters: dict | None,
        candidate_algorithm: str,
        requested_algorithm: str,
    ) -> dict | None:
        if not parameters:
            return None

        if requested_algorithm != "ensemble":
            return parameters

        candidate_parameters = parameters.get(
            candidate_algorithm
        )

        if candidate_parameters is None:
            return None

        if not isinstance(candidate_parameters, dict):
            raise ValueError(
                "Ensemble-Parameter müssen je Algorithmus "
                "als Dictionary angegeben werden."
            )

        return candidate_parameters

    @staticmethod
    def _split_frame(frame):
        train_end = int(len(frame) * 0.80)
        validation_end = int(len(frame) * 0.90)

        train = frame.iloc[:train_end]
        validation = frame.iloc[train_end:validation_end]
        test = frame.iloc[validation_end:]

        if train.empty or validation.empty or test.empty:
            raise ValueError(
                "Training, Validation und Test müssen jeweils "
                "mindestens eine Zeile enthalten."
            )

        return train, validation, test
