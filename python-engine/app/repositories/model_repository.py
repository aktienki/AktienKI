from __future__ import annotations

from datetime import datetime, timezone
from uuid import uuid4

from sqlalchemy import text


class ModelRepository:
    def __init__(self, session):
        self.session = session

    def get_or_create_definition(
        self,
        *,
        code: str,
        name: str,
        algorithm: str,
        target_name: str,
        interval: str,
        feature_version: str,
        default_parameters: dict,
    ) -> int:
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
                    "code": code,
                    "name": name,
                    "algorithm": algorithm,
                    "target_name": target_name,
                    "interval": interval,
                    "feature_version": feature_version,
                    "default_parameters": __import__("json").dumps(
                        default_parameters
                    ),
                },
            ).scalar_one()
        )

    def start_training_run(
        self,
        *,
        model_definition_id: int,
        instrument_id: int,
        feature_version: str,
        target_name: str,
        parameters: dict,
    ) -> tuple[int, str]:
        public_id = str(uuid4())

        run_id = int(
            self.session.execute(
                text(
                    """
                    INSERT INTO training_runs (
                        public_id,
                        model_definition_id,
                        instrument_id,
                        status,
                        feature_version,
                        target_name,
                        started_at,
                        parameters,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :public_id,
                        :model_definition_id,
                        :instrument_id,
                        'running',
                        :feature_version,
                        :target_name,
                        NOW(),
                        CAST(:parameters AS jsonb),
                        NOW(),
                        NOW()
                    )
                    RETURNING id
                    """
                ),
                {
                    "public_id": public_id,
                    "model_definition_id": model_definition_id,
                    "instrument_id": instrument_id,
                    "feature_version": feature_version,
                    "target_name": target_name,
                    "parameters": __import__("json").dumps(parameters),
                },
            ).scalar_one()
        )

        return run_id, public_id

    def save_trained_model(
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
                    "parameters": __import__("json").dumps(parameters),
                    "metrics": __import__("json").dumps(metrics),
                    "feature_names": __import__("json").dumps(feature_names),
                },
            ).scalar_one()
        )

    def complete_training_run(
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
                "metrics": __import__("json").dumps(metrics),
            },
        )

    def fail_training_run(self, *, run_id: int, error_message: str) -> None:
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
