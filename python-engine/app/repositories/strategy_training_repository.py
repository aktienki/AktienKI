from __future__ import annotations

import json
from uuid import uuid4

from sqlalchemy import text


class StrategyTrainingRepository:
    def __init__(self, session):
        self.session = session

    def get_or_create_definition(
        self,
        *,
        strategy_profile_id: int,
        strategy_profile_version: int,
        algorithm: str,
        target_name: str,
        interval: str,
        feature_version: str,
        default_parameters: dict,
    ) -> int:
        code = (
            f"strategy_{strategy_profile_id}_v{strategy_profile_version}_"
            f"{algorithm}_{target_name}"
        )

        row = self.session.execute(
            text(
                """
                SELECT id
                FROM model_definitions
                WHERE code = :code
                LIMIT 1
                """
            ),
            {"code": code},
        ).mappings().first()

        if row:
            return int(row["id"])

        return int(
            self.session.execute(
                text(
                    """
                    INSERT INTO model_definitions (
                        strategy_profile_id,
                        strategy_profile_version,
                        code,
                        name,
                        algorithm,
                        task_type,
                        target_name,
                        interval,
                        feature_version,
                        default_parameters,
                        is_active,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :strategy_profile_id,
                        :strategy_profile_version,
                        :code,
                        :name,
                        :algorithm,
                        'regression',
                        :target_name,
                        :interval,
                        :feature_version,
                        CAST(:default_parameters AS jsonb),
                        true,
                        NOW(),
                        NOW()
                    )
                    RETURNING id
                    """
                ),
                {
                    "strategy_profile_id": strategy_profile_id,
                    "strategy_profile_version": strategy_profile_version,
                    "code": code,
                    "name": code,
                    "algorithm": algorithm,
                    "target_name": target_name,
                    "interval": interval,
                    "feature_version": feature_version,
                    "default_parameters": json.dumps(
                        default_parameters
                    ),
                },
            ).scalar_one()
        )

    def start_run(
        self,
        *,
        strategy_profile_id: int,
        strategy_profile_version: int,
        model_definition_id: int,
        instrument_id: int,
        feature_version: str,
        target_name: str,
        parameters: dict,
        resolved_configuration: dict,
    ) -> tuple[int, str]:
        public_id = str(uuid4())

        run_id = int(
            self.session.execute(
                text(
                    """
                    INSERT INTO training_runs (
                        public_id,
                        strategy_profile_id,
                        strategy_profile_version,
                        model_definition_id,
                        instrument_id,
                        status,
                        feature_version,
                        target_name,
                        started_at,
                        parameters,
                        resolved_configuration,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :public_id,
                        :strategy_profile_id,
                        :strategy_profile_version,
                        :model_definition_id,
                        :instrument_id,
                        'running',
                        :feature_version,
                        :target_name,
                        NOW(),
                        CAST(:parameters AS jsonb),
                        CAST(:resolved_configuration AS jsonb),
                        NOW(),
                        NOW()
                    )
                    RETURNING id
                    """
                ),
                {
                    "public_id": public_id,
                    "strategy_profile_id": strategy_profile_id,
                    "strategy_profile_version": strategy_profile_version,
                    "model_definition_id": model_definition_id,
                    "instrument_id": instrument_id,
                    "feature_version": feature_version,
                    "target_name": target_name,
                    "parameters": json.dumps(parameters),
                    "resolved_configuration": json.dumps(
                        resolved_configuration
                    ),
                },
            ).scalar_one()
        )

        return run_id, public_id

    def save_model(
        self,
        *,
        model_definition_id: int,
        instrument_id: int,
        version: str,
        artifact_path: str,
        checksum: str,
        training_period_start,
        training_period_end,
        training_rows: int,
        validation_rows: int,
        test_rows: int,
        parameters: dict,
        metrics: dict,
        feature_names: list[str],
        metadata: dict,
    ) -> int:
        return int(
            self.session.execute(
                text(
                    """
                    INSERT INTO trained_models (
                        model_definition_id,
                        instrument_id,
                        scope,
                        version,
                        status,
                        storage_disk,
                        artifact_path,
                        checksum,
                        trained_at,
                        training_period_start,
                        training_period_end,
                        training_rows,
                        validation_rows,
                        test_rows,
                        parameters,
                        metrics,
                        feature_names,
                        metadata,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :model_definition_id,
                        :instrument_id,
                        'global',
                        :version,
                        'ready',
                        'local',
                        :artifact_path,
                        :checksum,
                        NOW(),
                        :training_period_start,
                        :training_period_end,
                        :training_rows,
                        :validation_rows,
                        :test_rows,
                        CAST(:parameters AS jsonb),
                        CAST(:metrics AS jsonb),
                        CAST(:feature_names AS jsonb),
                        CAST(:metadata AS jsonb),
                        NOW(),
                        NOW()
                    )
                    RETURNING id
                    """
                ),
                {
                    "model_definition_id": model_definition_id,
                    "instrument_id": instrument_id,
                    "version": version,
                    "artifact_path": artifact_path,
                    "checksum": checksum,
                    "training_period_start": training_period_start,
                    "training_period_end": training_period_end,
                    "training_rows": training_rows,
                    "validation_rows": validation_rows,
                    "test_rows": test_rows,
                    "parameters": json.dumps(parameters),
                    "metrics": json.dumps(metrics),
                    "feature_names": json.dumps(feature_names),
                    "metadata": json.dumps(metadata),
                },
            ).scalar_one()
        )

    def complete_run(
        self,
        *,
        run_id: int,
        trained_model_id: int,
        metrics: dict,
    ) -> None:
        self.session.execute(
            text(
                """
                UPDATE training_runs
                SET status = 'completed',
                    trained_model_id = :trained_model_id,
                    metrics = CAST(:metrics AS jsonb),
                    finished_at = NOW(),
                    updated_at = NOW()
                WHERE id = :run_id
                """
            ),
            {
                "run_id": run_id,
                "trained_model_id": trained_model_id,
                "metrics": json.dumps(metrics),
            },
        )

    def fail_run(
        self,
        *,
        run_id: int,
        error_message: str,
    ) -> None:
        self.session.execute(
            text(
                """
                UPDATE training_runs
                SET status = 'failed',
                    error_message = :error_message,
                    finished_at = NOW(),
                    updated_at = NOW()
                WHERE id = :run_id
                """
            ),
            {
                "run_id": run_id,
                "error_message": error_message[:10000],
            },
        )
